<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_device_actions_table
 *
 * Work-order / action-request table for the Kanban-style task board
 * in the Vue frontend. Each row represents one maintenance or production
 * action assigned to a device.
 *
 * Workflow states:  pending → approved → completed
 *                   pending → rejected  (terminal)
 *
 * FK to devices.device_id with CASCADE to keep data consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_actions', function (Blueprint $table): void {
            $table->integer('id')->autoIncrement();

            // Which device this action is for
            $table->string('device_id', 50);

            // Human-readable action label (e.g. "Replace mold insert")
            $table->string('action', 100);

            // Detailed description / instructions
            $table->text('details')->nullable();

            // Workflow status: pending | approved | rejected | completed
            $table->string('status', 20)->default('pending');

            // Display order within the board (lower = higher priority)
            $table->integer('priority')->default(0);

            // Creator — JWT username of the user who raised this action
            $table->string('created_by', 100)->nullable();

            // Approver — set when status transitions to 'approved'
            $table->string('approved_by', 100)->nullable();
            $table->dateTime('approved_at')->nullable();

            // Rejection reason — set when status transitions to 'rejected'
            $table->text('notes')->nullable();

            $table->timestamps(); // created_at + updated_at (standard Eloquent)

            // FK: CASCADE keeps actions in sync if a device is removed
            $table->foreign('device_id')
                  ->references('device_id')
                  ->on('devices')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });

        // Index for common query patterns
        Schema::table('device_actions', function (Blueprint $table): void {
            $table->index('device_id',  'idx_da_device_id');
            $table->index('status',     'idx_da_status');
            $table->index('priority',   'idx_da_priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_actions');
    }
};
