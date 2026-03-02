<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_device_status_table
 *
 * Live connection/threshold status per device.
 * PK is device_id (varchar) — 1:1 with devices.
 * FK CASCADE on devices — if device is deleted, status row is deleted too.
 *
 * Must run AFTER create_devices_table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_status', function (Blueprint $table): void {
            // PK = device_id (string FK) — no auto-increment id
            $table->string('device_id', 50)->primary();

            $table->dateTime('last_heartbeat')->nullable();
            $table->string('connection_status', 20)->default('Disconnected');
            $table->string('threshold_status', 20)->default('Normal');

            // Tracks last time a Discord disconnect notification was sent
            $table->dateTime('last_disconnect_notification')->nullable();

            // Tracks last time a Discord threshold breach notification was sent
            $table->dateTime('last_threshold_notification')->nullable();

            // FK: CASCADE on both delete and update to stay in sync with devices
            $table->foreign('device_id')
                  ->references('device_id')
                  ->on('devices')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_status');
    }
};
