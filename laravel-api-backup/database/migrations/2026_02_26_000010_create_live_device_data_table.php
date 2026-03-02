<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_live_device_data_table
 *
 * Cache table for pre-computed live metrics per device.
 * PK = device_id (string) — 1:1 with devices.
 * Written by a background IoT pipeline; read by DeviceController::live().
 *
 * `live_data` contains a JSON object with pre-computed efficiency, output,
 * cycle_time, status etc. for fast dashboard reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_device_data', function (Blueprint $table): void {
            // PK = device_id string — no auto-increment
            $table->string('device_id', 50)->primary();
            $table->string('display_type', 50)->nullable();

            // Pre-computed live metrics JSON
            $table->longText('live_data')->nullable();

            // Auto-updated by DB on every write — no Eloquent timestamps
            $table->timestamp('last_updated')
                  ->useCurrent()
                  ->useCurrentOnUpdate();
        });

        // json_valid CHECK constraint on live_data
        Schema::table('live_device_data', function (Blueprint $table): void {
            $table->check('live_data IS NULL OR json_valid(`live_data`)', 'chk_live_data_json');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_device_data');
    }
};
