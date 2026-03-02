<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_tuft_table
 *
 * IoT data from tufting machines.
 * `output` = pcs produced in this cycle/interval.
 * `process` = sub-process label (may differ from devices.process).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuft', function (Blueprint $table): void {
            $table->integer('id')->autoIncrement();
            $table->timestamp('datetime');
            $table->string('uuid', 50)->unique();
            $table->string('device_id', 50)->nullable();
            $table->string('product', 50)->nullable();
            $table->string('process', 50)->nullable();
            $table->integer('output')->nullable(); // pcs produced

            $table->index(['device_id', 'datetime'], 'idx_device_id_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuft');
    }
};
