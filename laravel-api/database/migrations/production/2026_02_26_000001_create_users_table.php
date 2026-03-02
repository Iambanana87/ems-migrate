<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: create_users_table
 *
 * Mirrors the legacy "production" database schema EXACTLY.
 * Note: The legacy table has no `updated_at` column — preserved intentionally.
 * Role enum matches legacy values ('admin', 'user').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            // Legacy: id int(11) unsigned NOT NULL AUTO_INCREMENT
            $table->unsignedInteger('id')->autoIncrement();

            // Legacy: username varchar(50) NOT NULL UNIQUE
            $table->string('username', 50)->unique();

            // Legacy: password varchar(255) NOT NULL (bcrypt)
            $table->string('password', 255);

            // Legacy: created_at timestamp NOT NULL DEFAULT current_timestamp()
            // Using timestamp() with useCurrent() — no updated_at
            $table->timestamp('created_at')->useCurrent();

            // Legacy: role enum('admin','user') DEFAULT 'user'
            // NOTE: manage_users.php also writes 'maintenance' in application logic
            // but the DB schema only defines 'admin'|'user'.
            // We preserve the legacy enum exactly here.
            $table->enum('role', ['admin', 'user'])->default('user');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
