<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_devices_table
 *
 * The central registry of all monitored equipment.
 * Must run BEFORE device_status (FK constraint).
 *
 * No created_at / updated_at — the legacy schema has neither.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->integer('id')->autoIncrement();

            $table->string('device_id', 50)->unique();
            $table->string('display_type', 20);            // 'mold' | 'tuft' | 'blister'
            $table->string('data_source', 20)->nullable();
            $table->string('product', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('manufacturer', 100)->nullable();
            $table->date('manufacturing_date')->nullable();
            $table->integer('cavities')->nullable();        // mold: # of cavities
            $table->integer('hole_per_brush')->nullable();  // tuft
            $table->string('process', 50)->nullable();      // family/process label
            $table->string('metric_name', 50)->nullable();
            $table->decimal('lower_limit', 10, 2)->nullable();
            $table->decimal('target_limit', 10, 2)->nullable(); // efficiency target
            $table->decimal('upper_limit', 10, 2)->nullable();
            $table->integer('frequency')->default(60);         // polling frequency (s)
            $table->integer('freq_check_limit')->default(300); // heartbeat timeout (s)
            $table->integer('capacity')->nullable();           // pre-computed capacity
            $table->string('mold_type', 110)->nullable();
            $table->decimal('efficiency_upper_limit', 10, 2)->nullable();
            $table->decimal('efficiency_lower_limit', 8, 1)->default(0);
            $table->boolean('flex')->default(false);           // flexible / multi-product
            $table->integer('brushes_per_cycle')->nullable();  // blister

            // Cumulative counters — written by IoT pipeline
            $table->unsignedBigInteger('total_count')->default(0);
            $table->string('unit', 16)->default('');
            $table->dateTime('total_count_updated_at')->nullable();

            $table->unsignedBigInteger('cavity_count')->default(0);
            $table->dateTime('cavity_count_updated_at')->nullable();

            $table->unsignedBigInteger('total_rpm')->default(0);
            $table->dateTime('total_rpm_updated_at')->nullable();

            $table->unsignedBigInteger('total_cycle')->default(0);
            $table->dateTime('total_cycle_updated_at')->nullable();

            $table->integer('history_count')->nullable(); // # of records used for stats
            $table->dateTime('history_count_updated_at')->nullable();

            $table->string('client', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
