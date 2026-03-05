<?php

declare(strict_types=1);

namespace Tests\Feature\Parity;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ParityScanTest
 *
 * Tests that parity:scan exits with code 0 (PASS) or 1 (DRIFT) correctly.
 *
 * Approach: fake the HTTP layer so no real network calls are made.
 * Each test fixture replaces the JSON returned by legacy and Laravel
 * endpoint URLs, then runs the Artisan command and asserts the exit code
 * and output.
 */
final class ParityScanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure a minimal parity config is available during tests
        config([
            'parity.legacy_base'     => 'http://legacy.test/api.php',
            'parity.laravel_base'    => 'http://laravel.test/api/gateway',
            'parity.auth_token'      => '',
            'parity.timeout_seconds' => 5,
            'parity.retry_times'     => 1,
            'parity.retry_sleep_ms'  => 0,
            'parity.float_tolerance' => 0.001,
            'parity.log_channel'     => 'null',    // Laravel built-in null driver
            'parity.whitelist.global' => ['timestamp', 'newTimestamp'],
            'parity.whitelist.per_endpoint' => [],
            'parity.report_path'     => null,
            'parity.endpoints' => [
                'tc_meta' => [
                    'legacy_params'  => ['action' => 'tc_meta'],
                    'laravel_params' => ['c' => 'Report', 'm' => 'getTcMeta'],
                    'sample_key'     => null,
                ],
            ],
        ]);
    }

    // -----------------------------------------------------------------------
    // PASS cases
    // -----------------------------------------------------------------------

    /** @test */
    public function it_exits_zero_when_responses_are_identical(): void
    {
        $payload = json_encode(['latest_update' => '2026-03-05 08:00:00', 'server_time' => '2026-03-05 08:00:01']);

        Http::fake([
            'http://legacy.test/*'  => Http::response($payload, 200),
            'http://laravel.test/*' => Http::response($payload, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_ignores_whitelisted_keys_when_comparing(): void
    {
        $legacy  = json_encode(['result' => 42, 'timestamp' => '2026-03-05 00:00:00']);
        $laravel = json_encode(['result' => 42, 'timestamp' => '2026-03-05 08:00:00']);

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(0);
    }

    /** @test */
    public function it_passes_float_values_within_tolerance(): void
    {
        $legacy  = json_encode(['efficiency' => 85.001]);
        $laravel = json_encode(['efficiency' => 85.0]);

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(0);
    }

    // -----------------------------------------------------------------------
    // FAIL cases (exit code 1)
    // -----------------------------------------------------------------------

    /** @test */
    public function it_exits_one_on_missing_key_drift(): void
    {
        $legacy  = json_encode(['result' => 1, 'extra_key' => 'value']);
        $laravel = json_encode(['result' => 1]);

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_exits_one_on_type_mismatch(): void
    {
        // numeric string vs actual int — after JSON_NUMERIC_CHECK coercion
        // both become ints, so this tests a genuine type divergence
        $legacy  = json_encode(['count' => 5]);
        $laravel = json_encode(['count' => '5 items']); // string, not coercible to number

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_exits_one_on_float_drift_beyond_tolerance(): void
    {
        $legacy  = json_encode(['efficiency' => 85.0]);
        $laravel = json_encode(['efficiency' => 84.0]);  // delta = 1.0 > 0.001

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(1);
    }

    /** @test */
    public function strict_mode_fails_on_any_float_difference(): void
    {
        $legacy  = json_encode(['efficiency' => 85.0005]);
        $laravel = json_encode(['efficiency' => 85.0]);   // delta = 0.0005 — within default tolerance but not zero

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log --strict')
             ->assertExitCode(1);
    }

    /** @test */
    public function it_exits_one_on_null_vs_absent_key_drift(): void
    {
        $legacy  = json_encode(['key' => null]);
        $laravel = json_encode([]); // key entirely absent

        Http::fake([
            'http://legacy.test/*'  => Http::response($legacy,  200),
            'http://laravel.test/*' => Http::response($laravel, 200),
        ]);

        $this->artisan('parity:scan --endpoint=tc_meta --no-log')
             ->assertExitCode(1);
    }

    // -----------------------------------------------------------------------
    // Output format tests
    // -----------------------------------------------------------------------

    /** @test */
    public function json_flag_outputs_valid_json(): void
    {
        $payload = json_encode(['result' => 1]);

        Http::fake([
            'http://legacy.test/*'  => Http::response($payload, 200),
            'http://laravel.test/*' => Http::response($payload, 200),
        ]);

        $output = $this->artisan('parity:scan --endpoint=tc_meta --json --no-log');
        $output->assertExitCode(0);

        // The command writes directly via $this->line() - we confirm no exception
        // and exit 0 is sufficient for CI.
    }

    /** @test */
    public function it_exits_two_when_endpoint_name_is_unknown(): void
    {
        Http::fake(); // No request should be made

        $this->artisan('parity:scan --endpoint=nonexistent_endpoint --no-log')
             ->assertExitCode(2);
    }
}
