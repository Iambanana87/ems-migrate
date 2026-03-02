<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * User Eloquent Model
 *
 * Mirrors the legacy `users` table in the "production" database.
 *
 * @property int         $id
 * @property string      $username
 * @property string      $password
 * @property string      $role       'admin' | 'user'
 * @property \Carbon\Carbon $created_at
 */
class User extends Authenticatable
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | TABLE / TIMESTAMPS
    |--------------------------------------------------------------------------
    | Legacy table has NO `updated_at` column. Disable Laravel's dual-timestamp
    | convention and use `UPDATED_AT = null` so Eloquent never tries to write it.
    */

    protected $table = 'users';

    /**
     * Disable automatic `updated_at` management.
     * The legacy schema has no such column.
     */
    public const UPDATED_AT = null;

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'username',
        'password',
        'role',
    ];

    /*
    |--------------------------------------------------------------------------
    | HIDDEN ATTRIBUTES (never serialised in JSON responses)
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    | `role` is stored as an ENUM in MySQL. We expose it as a plain string
    | rather than a BackedEnum so that the gateway can supply 'maintenance'
    | (written by manage_users.php) without type errors.
    |
    | `hashed` cast for password ensures Eloquent auto-hashes on assignment
    | (e.g. $user->password = 'plain' → stored as bcrypt).
    |--------------------------------------------------------------------------
    */

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'         => 'integer',
            'password'   => 'hashed',
            'created_at' => 'datetime',
            'role'       => 'string',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS / HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Check if this user holds a specific role.
     * Case-insensitive for defensive programming.
     */
    public function isRole(string $role): bool
    {
        return strtolower($this->role) === strtolower($role);
    }

    /**
     * Convenience: is this user an admin?
     */
    public function isAdmin(): bool
    {
        return $this->isRole('admin');
    }

    /*
    |--------------------------------------------------------------------------
    | AUTHENTICATION COLUMN OVERRIDE
    |--------------------------------------------------------------------------
    | Legacy system identifies users by `username`, not `email`.
    */

    public function getAuthIdentifierName(): string
    {
        return 'username';
    }
}
