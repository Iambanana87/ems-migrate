<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_mold_table
 *
 * IoT data from injection-mold machines.
 * Written by device firmware. Read by DeviceService for live stats.
 *
 * Note: Legacy used utf8mb3 charset. We use utf8mb4 for consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mold', function (Blueprint $table): void {
            $table->integer('id')->autoIncrement();
            $table->timestamp('datetime');       // Record timestamp from device
            $table->string('uuid', 50)->unique(); // Device-generated dedup key
            $table->string('device_id', 50)->nullable();
            $table->string('product', 100)->nullable();
            $table->integer('cavities')->nullable();
            $table->decimal('cycle_time', 11, 2)->nullable(); // Seconds

            // Composite index for efficient time-range queries per device
            $table->index(['device_id', 'datetime'], 'idx_mold_id_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mold');
    }
};
