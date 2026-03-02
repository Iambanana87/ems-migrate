<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_blister_table
 *
 * IoT data from blister-packaging machines.
 *
 * ⚠️ Two unusual column types — preserved exactly from legacy:
 *   - BrushesperCycle: varchar(10) DEFAULT 'N/A'  (can hold 'N/A' string!)
 *   - output:          varchar(10) DEFAULT NULL    (can hold non-numeric values)
 *
 * The legacy PHP code casts these to int/float at application level.
 * We preserve the schema exactly and handle casting in the Blister model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blister', function (Blueprint $table): void {
            $table->integer('id')->autoIncrement();
            $table->timestamp('datetime')->nullable();
            $table->string('uuid', 50)->unique();
            $table->string('device_id', 50);
            $table->string('product', 100);
            // Legacy varchar: can store 'N/A' — NOT an integer column
            $table->string('BrushesperCycle', 10)->default('N/A');
            $table->integer('cyclecount');
            // Legacy varchar: can store non-numeric during error states
            $table->string('output', 10)->nullable();

            $table->index(['device_id', 'datetime'], 'idx_device_id_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blister');
    }
};
