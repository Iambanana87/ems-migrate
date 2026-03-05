<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: add_mold_id_alias_to_mold_table
 *
 * The legacy api.php queries the `mold` table using `mold_id` as the device
 * identifier column (e.g., `BINARY t.mold_id = BINARY d.device_id`).
 * However, the current `mold` table uses `device_id` as the column name.
 *
 * This migration adds a VIRTUAL GENERATED COLUMN `mold_id` that mirrors
 * `device_id`, allowing the legacy SQL in api.php to work without modification
 * and restoring parity scan stability.
 *
 * Schema-only fix — no business logic changes.
 * Safe to run against existing data (VIRTUAL columns require no data rewrite).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only add if it doesn't already exist
        $columns = Schema::getColumnListing('mold');
        if (!in_array('mold_id', $columns, true)) {
            DB::statement(
                'ALTER TABLE `mold` ADD COLUMN `mold_id` VARCHAR(50) GENERATED ALWAYS AS (`device_id`) VIRTUAL'
            );
            DB::statement(
                'ALTER TABLE `mold` ADD INDEX `idx_mold_id` (`mold_id`)'
            );
        }
    }

    public function down(): void
    {
        $columns = Schema::getColumnListing('mold');
        if (in_array('mold_id', $columns, true)) {
            DB::statement('ALTER TABLE `mold` DROP INDEX `idx_mold_id`');
            DB::statement('ALTER TABLE `mold` DROP COLUMN `mold_id`');
        }
    }
};
