<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Parity\FloatComparator;
use App\Services\Parity\ParityDiffer;
use App\Services\Parity\ParityNormalizer;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * ParityScan — parity:scan
 *
 * Compares legacy PHP endpoint responses against Laravel gateway endpoint
 * responses and reports any structural or semantic drift.
 *
 * USAGE:
 *   # Scan all endpoints defined in config/parity.php
 *   php artisan parity:scan
 *
 *   # Scan a single endpoint by name
 *   php artisan parity:scan --endpoint=machine_details.mold
 *
 *   # Strict mode (zero float tolerance)
 *   php artisan parity:scan --strict
 *
 *   # Machine-readable JSON output
 *   php artisan parity:scan --json
 *
 *   # Check key ordering as well
 *   php artisan parity:scan --check-order
 *
 *   # Add extra query parameters at runtime
 *   php artisan parity:scan --endpoint=machine_details.mold --param=client:ACME
 *
 *   # Limit to first N items in a list response
 *   php artisan parity:scan --endpoint=machine_details.mold --limit=10
 *
 * EXIT CODES:
 *   0 — No drift detected
 *   1 — Drift detected (CI gate failure)
 *   2 — Configuration or network error
 */
final class ParityScan extends Command
{
    protected $signature = 'parity:scan
        {--endpoint=       : Endpoint name from config/parity.php (omit to scan all)}
        {--param=*         : Extra query param in key:value format, repeatable}
        {--limit=          : Sample first N items from list responses}
        {--strict          : Zero float tolerance (exact match required)}
        {--check-order     : Report key ordering differences}
        {--json            : Output machine-readable JSON report instead of table}
        {--save            : Save JSON report to config(parity.report_path)}
        {--no-log          : Suppress Laravel log channel writes}
    ';

    protected $description = 'Compare legacy and Laravel endpoint responses for API parity drift';

    /** @var array<string, array<string, mixed>> Accumulated scan results keyed by endpoint name */
    private array $results = [];

    private int $totalDrifts = 0;

    public function handle(): int
    {
        $endpointFilter = $this->option('endpoint') ?: null;
        $endpoints      = config('parity.endpoints', []);

        if (empty($endpoints)) {
            $this->error('No endpoints defined in config/parity.php');
            return 2;
        }

        if ($endpointFilter !== null && !isset($endpoints[$endpointFilter])) {
            $this->error("Endpoint '{$endpointFilter}' not found in config/parity.php");
            $this->info('Available: ' . implode(', ', array_keys($endpoints)));
            return 2;
        }

        $toScan = $endpointFilter !== null
            ? [$endpointFilter => $endpoints[$endpointFilter]]
            : $endpoints;

        foreach ($toScan as $name => $config) {
            $this->scanEndpoint($name, $config);
        }

        $this->renderReport();
        $this->persistReport();

        return $this->totalDrifts > 0 ? 1 : 0;
    }

    // -----------------------------------------------------------------------
    // Scan one endpoint
    // -----------------------------------------------------------------------

    private function scanEndpoint(string $name, array $endpointConfig): void
    {
        $this->line("→ Scanning <info>{$name}</info>...");

        // Build query params
        $legacyParams  = array_merge(
            $endpointConfig['legacy_params'] ?? [],
            $this->extraParams()
        );
        $laravelParams = array_merge(
            $endpointConfig['laravel_params'] ?? [],
            $this->extraParams()
        );

        // Fetch both sides
        try {
            $legacyRaw  = $this->fetch(config('parity.legacy_base'), $legacyParams);
            $laravelRaw = $this->fetch(config('parity.laravel_base'), $laravelParams);
        } catch (\Throwable $e) {
            $this->results[$name] = [
                'status' => 'ERROR',
                'error'  => 'Fetch failed: ' . $e->getMessage(),
                'drifts' => [],
            ];
            $this->error("  ✗ FETCH ERROR: {$e->getMessage()}");
            return;
        }

        // Build services
        $globalWhitelist   = config('parity.whitelist.global', []);
        $endpointWhitelist = config("parity.whitelist.per_endpoint.{$name}", []);

        $tolerance = $this->option('strict')
            ? 0.0
            : (float) config('parity.float_tolerance', 0.001);

        $normalizer = new ParityNormalizer($globalWhitelist, $endpointWhitelist);
        $comparator = new FloatComparator($tolerance);
        $differ     = new ParityDiffer($comparator, (bool) $this->option('check-order'));

        // Normalize
        try {
            $legacyNorm  = $normalizer->normalize($legacyRaw);
            $laravelNorm = $normalizer->normalize($laravelRaw);
        } catch (JsonException $e) {
            $this->results[$name] = [
                'status' => 'ERROR',
                'error'  => 'JSON decode failed: ' . $e->getMessage(),
                'drifts' => [],
            ];
            $this->error("  ✗ JSON ERROR: {$e->getMessage()}");
            return;
        }

        // Apply --limit sampling
        $legacyNorm  = $this->applyLimit($legacyNorm, $endpointConfig['sample_key'] ?? null);
        $laravelNorm = $this->applyLimit($laravelNorm, $endpointConfig['sample_key'] ?? null);

        // Diff
        $drifts = $differ->diff($legacyNorm, $laravelNorm);

        $status = count($drifts) === 0 ? 'PASS' : 'FAIL';
        $this->totalDrifts += count($drifts);

        $this->results[$name] = [
            'status'      => $status,
            'drift_count' => count($drifts),
            'drifts'      => $drifts,
            'legacy_url'  => config('parity.legacy_base') . '?' . http_build_query($legacyParams),
            'laravel_url' => config('parity.laravel_base') . '?' . http_build_query($laravelParams),
        ];

        if ($status === 'PASS') {
            $this->line("  <fg=green>✓ PASS</fg=green> (0 drifts)");
        } else {
            $this->line("  <fg=red>✗ FAIL</fg=red> (" . count($drifts) . " drifts)");
            if (!$this->option('json')) {
                foreach ($drifts as $d) {
                    $this->line("    <fg=yellow>[{$d['type']}]</fg=yellow> {$d['path']} — {$d['detail']}");
                }
            }
        }

        // Log result
        if (!$this->option('no-log')) {
            Log::channel(config('parity.log_channel', 'daily'))
                ->info("parity:scan [{$name}] {$status}", [
                    'endpoint'    => $name,
                    'status'      => $status,
                    'drift_count' => count($drifts),
                    'drifts'      => $drifts,
                ]);
        }
    }

    // -----------------------------------------------------------------------
    // HTTP fetch with retry
    // -----------------------------------------------------------------------

    private function fetch(string $baseUrl, array $params): string
    {
        $token   = config('parity.auth_token', '');
        $timeout = (int) config('parity.timeout_seconds', 10);
        $retries = (int) config('parity.retry_times', 2);
        $sleep   = (int) config('parity.retry_sleep_ms', 300);

        $client = Http::timeout($timeout)
            ->retry($retries, $sleep, throw: true);

        if ($token !== '') {
            $client = $client->withToken($token);
        }

        $response = $client->get($baseUrl, $params);

        if (!$response->successful()) {
            throw new ConnectionException(
                "HTTP {$response->status()} from {$baseUrl}: " . $response->body()
            );
        }

        return $response->body();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Parse --param=key:value options into an associative array.
     *
     * @return array<string, string>
     */
    private function extraParams(): array
    {
        $extra = [];
        foreach ((array) $this->option('param') as $raw) {
            if (str_contains($raw, ':')) {
                [$k, $v] = explode(':', $raw, 2);
                $extra[trim($k)] = trim($v);
            }
        }
        return $extra;
    }

    /**
     * Limit a list response to the first N items (--limit option).
     * When sample_key is set, limits that nested list.
     */
    private function applyLimit(mixed $data, ?string $sampleKey): mixed
    {
        $limit = $this->option('limit');
        if ($limit === null) {
            return $data;
        }

        $limit = (int) $limit;

        if ($sampleKey !== null) {
            $keys = explode('.', $sampleKey);
            $ref  = &$data;
            foreach ($keys as $k) {
                if (!isset($ref[$k]) || !is_array($ref[$k])) {
                    return $data;
                }
                $ref = &$ref[$k];
            }
            $ref = array_slice($ref, 0, $limit);
            return $data;
        }

        if (is_array($data) && array_is_list($data)) {
            return array_slice($data, 0, $limit);
        }

        return $data;
    }

    // -----------------------------------------------------------------------
    // Output
    // -----------------------------------------------------------------------

    private function renderReport(): void
    {
        $this->newLine();

        if ($this->option('json')) {
            $this->line(json_encode($this->buildReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return;
        }

        $this->line('═══════════════════════════════════════');
        $this->line("  PARITY SCAN SUMMARY");
        $this->line('═══════════════════════════════════════');

        $rows = [];
        foreach ($this->results as $name => $result) {
            $rows[] = [
                $name,
                $result['status'],
                $result['drift_count'] ?? 'N/A',
                $result['error'] ?? '',
            ];
        }

        $this->table(['Endpoint', 'Status', 'Drifts', 'Error'], $rows);

        if ($this->totalDrifts === 0) {
            $this->info('✓ FULL API PARITY CERTIFIED — ZERO DRIFT');
        } else {
            $this->error("✗ DRIFT DETECTED — {$this->totalDrifts} total drifts across " . count($this->results) . ' endpoints');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReport(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'total_drifts' => $this->totalDrifts,
            'verdict'      => $this->totalDrifts === 0 ? 'FULL_API_PARITY_CERTIFIED' : 'DRIFT_DETECTED',
            'endpoints'    => $this->results,
        ];
    }

    private function persistReport(): void
    {
        if (!$this->option('save')) {
            return;
        }

        $reportPath = rtrim((string) config('parity.report_path', storage_path('logs/parity')), '/');

        if (!is_dir($reportPath)) {
            mkdir($reportPath, 0755, true);
        }

        $filename = $reportPath . '/parity-scan-' . now()->format('Y-m-d_His') . '.json';
        file_put_contents($filename, json_encode($this->buildReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->line("Report saved → {$filename}");
    }
}
