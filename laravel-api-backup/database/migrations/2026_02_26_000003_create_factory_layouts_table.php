<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_factory_layouts_table
 *
 * Target: "production" database (default connection).
 *
 * Schema mirrors the legacy DDL exactly:
 *   - layout_json longtext with CHECK (json_valid(layout_json))
 *   - created_at / updated_at as timestamps (NOT Laravel's default dateTime)
 *   - Indexes on created_by and updated_at (used by list queries)
 *   - No `updated_at` auto-managed by Model — table uses ON UPDATE trigger
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factory_layouts', function (Blueprint $table): void {
            // Legacy: id int(11) NOT NULL AUTO_INCREMENT
            $table->integer('id')->autoIncrement();

            // Legacy: name varchar(255) NOT NULL
            $table->string('name', 255);

            // Legacy: description varchar(500) DEFAULT NULL
            $table->string('description', 500)->nullable();

            // Legacy: layout_json longtext CHECK (json_valid(layout_json))
            // Blueprint doesn't have a native longText + check shorthand,
            // so we add the MariaDB/MySQL check constraint via rawColumn.
            $table->longText('layout_json');

            // Legacy: canvas_w int(11) DEFAULT NULL
            $table->integer('canvas_w')->nullable();

            // Legacy: canvas_h int(11) DEFAULT NULL
            $table->integer('canvas_h')->nullable();

            // Legacy: created_by varchar(100) DEFAULT NULL
            // Stores the JWT username of the creator — not a FK to users table
            // (mirrors legacy design where created_by is a denormalised string).
            $table->string('created_by', 100)->nullable();

            // Legacy: created_at timestamp NULL DEFAULT current_timestamp()
            $table->timestamp('created_at')->nullable()->useCurrent();

            // Legacy: updated_at timestamp NULL DEFAULT current_timestamp()
            //         ON UPDATE current_timestamp()
            $table->timestamp('updated_at')
                  ->nullable()
                  ->useCurrent()
                  ->useCurrentOnUpdate();
        });

        // Add the json_valid CHECK constraint (MariaDB / MySQL 8.0.16+)
        // Blueprint's check() method generates: CONSTRAINT ... CHECK (expr)
        Schema::table('factory_layouts', function (Blueprint $table): void {
            $table->check('json_valid(`layout_json`)', 'chk_layout_json_valid');
        });

        // Indexes from legacy DDL
        Schema::table('factory_layouts', function (Blueprint $table): void {
            $table->index('created_by',  'idx_fl_created_by');
            $table->index('updated_at',  'idx_fl_updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factory_layouts');
    }
};
