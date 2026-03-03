<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * UserService
 * 
 * Handles user-related operations with strict parity to legacy api.php.
 */
class UserService
{
    /**
     * list_users: Returns id and username of all users.
     * STRICT PARITY with api.php:2112-2116.
     * 
     * @return array
     */
    public function listUsers(): array
    {
        // SELECT id, username FROM users ORDER BY username
        return DB::select("SELECT id, username FROM users ORDER BY username");
    }
}
