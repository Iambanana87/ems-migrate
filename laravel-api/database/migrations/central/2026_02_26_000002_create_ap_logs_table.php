<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_ap_logs_table
 *
 * Target: the "central" database (NOT "production").
 * This table is written to by ESP32 devices via log_data.php and
 * read by ap_status_api.php to compute per-AP live status.
 *
 * Schema reconstructed from field usage in:
 *   - backend/ap_status_api.php  (reads)
 *   - log_data.php               (writes — ap_name, location, status, logged_at)
 *
 * NOTE: Schema::connection('central') targets the "central" DB connection
 * defined in config/database.php. Run with:
 *   php artisan migrate --database=central
 */
return new class extends Migration
{
    /**
     * Run on the "central" database connection, not the default "production".
     */
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('ap_logs', function (Blueprint $table): void {
            $table->integerIncrements('id');

            // AP identifier — matches device names like "AP01", "AP_PG_01"
            $table->string('ap_name', 50);

            // Physical location label (e.g. "PG", "Assembly")
            $table->string('location', 50)->nullable();

            // Raw status string as received from the device.
            // Values seen in production: ONLINE, OFFLINE, CONNECTED,
            // DISCONNECTED, DISCONNECT, BREACHED, OK
            $table->string('status', 30);

            // Round-trip ping in milliseconds. Nullable — older firmware
            // did not report ping_ms.
            $table->decimal('ping_ms', 8, 2)->nullable();

            // Timestamp of the log record.
            // Legacy used `logged_at` (not created_at) — preserved exactly.
            $table->timestamp('logged_at')->useCurrent();
        });

        // Composite index used by the latest-per-AP subquery:
        //   SELECT ap_name, MAX(logged_at) ... GROUP BY ap_name
        Schema::connection('central')->table('ap_logs', function (Blueprint $table): void {
            $table->index(['ap_name', 'logged_at'], 'idx_ap_name_logged_at');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('ap_logs');
    }
};
