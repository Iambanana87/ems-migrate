<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditTrail;

/**
 * AuditService
 *
 * Cross-cutting write service — logs mutations to the `audit_trail` table.
 * Injected into any service that performs mutations: DeviceService,
 * DeviceActionService, FactoryLayoutService (extended later), etc.
 *
 * Mirrors the legacy model/audit.php AuditTrail class exactly:
 *   - Computes before/after JSON diffs (skips created_at / updated_at)
 *   - Guards against no-op writes (empty diff → no record inserted)
 *   - Generates UUID v4 without external dependencies
 *   - Keeps logic pure and testable: computeDiff() has no side-effects
 *
 * USAGE (in a Service class):
 *   public function __construct(private readonly AuditService $audit) {}
 *
 *   $before = $device->toArray();
 *   $device->update([...]);
 *   $this->audit->log('device_update', $reason, $before, $device->fresh()->toArray(), $who);
 */
final class AuditService
{
    /*
    |--------------------------------------------------------------------------
    | FIELDS EXCLUDED FROM DIFF COMPUTATION
    |--------------------------------------------------------------------------
    | Mirrors legacy: the AuditTrail class skips these when comparing before/after
    | so that routine timestamp updates don't create audit noise.
    */
    private const IGNORED_FIELDS = ['created_at', 'updated_at'];

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    */

    /**
     * Compute the diff between $before and $after and insert an audit record.
     *
     * If the diff is empty (no fields actually changed) this method returns
     * silently without inserting — matching legacy no-op guard behaviour.
     *
     * @param  string                $type    Mutation category (e.g. 'device_update')
     * @param  string|null           $reason  Human-readable note from the acting user
     * @param  array<string, mixed>  $before  Model attributes before mutation
     * @param  array<string, mixed>  $after   Model attributes after mutation
     * @param  string                $who     JWT username of the acting user
     */
    public function log(
        string  $type,
        ?string $reason,
        array   $before,
        array   $after,
        string  $who,
    ): void {
        $diff = $this->computeDiff($before, $after);

        // Legacy no-op guard: "Only logs diffs with actual changes"
        if (empty($diff)) {
            return;
        }

        AuditTrail::create([
            'id'     => $this->generateUuid(),
            'type'   => $type,
            'reason' => $reason,
            'who'    => $who,
            'diff'   => $diff,  // array → auto JSON-encoded by 'array' cast
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DIFF COMPUTATION
    |--------------------------------------------------------------------------
    */

    /**
     * Compare two attribute arrays and return only the fields that changed.
     *
     * Output shape (mirrors legacy):
     *   {
     *     "field_name": { "before": <old_value>, "after": <new_value> },
     *     ...
     *   }
     *
     * Rules (exact parity with legacy AuditTrail::compute_diff()):
     *   - Only top-level keys are compared (no deep diff).
     *   - Keys in IGNORED_FIELDS are skipped entirely.
     *   - Keys present only in $after (new fields) are included.
     *   - Keys present only in $before (deleted fields) are included.
     *   - Comparison uses loose equality (==) matching legacy PHP behaviour.
     *
     * Pure function — no side effects, safe to call in tests without DB.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{before: mixed, after: mixed}>
     */
    public function computeDiff(array $before, array $after): array
    {
        $diff = [];

        // Union of all keys from both snapshots
        $allKeys = array_unique([...array_keys($before), ...array_keys($after)]);

        foreach ($allKeys as $key) {
            // Skip noise fields
            if (in_array($key, self::IGNORED_FIELDS, strict: true)) {
                continue;
            }

            $oldVal = $before[$key] ?? null;
            $newVal = $after[$key]  ?? null;

            // Loose == mirrors the PHP == used in legacy compute_diff()
            // This correctly handles null vs '', 0 vs false, etc.
            if ($oldVal != $newVal) {
                $diff[$key] = [
                    'before' => $oldVal,
                    'after'  => $newVal,
                ];
            }
        }

        return $diff;
    }

    /*
    |--------------------------------------------------------------------------
    | UUID GENERATION
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a UUID v4 string.
     *
     * Mirrors legacy:
     *   sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
     *     mt_rand(0,0xffff), mt_rand(0,0xffff), ...)
     *
     * Uses random_bytes() (CSPRNG) instead of mt_rand() — cryptographically
     * secure while maintaining the same output format.
     *
     * Format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     */
    public function generateUuid(): string
    {
        $data = random_bytes(16);

        // Set version to 4 (random) and variant bits
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
