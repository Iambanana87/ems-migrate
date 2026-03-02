<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditTrail Eloquent Model
 *
 * Represents an immutable audit log record in the `audit_trail` table.
 * Records are written by AuditService and read by AuditTrailController.
 *
 * ⚠️ Three non-standard Eloquent settings required:
 *   1. $primaryKey stays 'id' but $keyType = 'string' (UUID, not integer)
 *   2. $incrementing = false  — disables auto-increment assumption
 *   3. $timestamps = false    — only created_at exists (table has no updated_at)
 *
 * @property string          $id          UUID v4 string
 * @property string          $type        Mutation category (e.g. 'device_update')
 * @property string|null     $reason      Human-readable note from actor
 * @property string          $who         JWT username of actor
 * @property \Carbon\Carbon  $created_at  Immutable write timestamp
 * @property array           $diff        JSON diff: { field: { before, after } }
 */
class AuditTrail extends Model
{
    use HasFactory;

    protected $table = 'audit_trail';

    /*
    |--------------------------------------------------------------------------
    | PRIMARY KEY — UUID string, not auto-increment integer
    |--------------------------------------------------------------------------
    */
    protected $primaryKey  = 'id';
    protected $keyType     = 'string';   // ← UUID is varchar(100)
    public    $incrementing = false;     // ← never let Eloquent auto-assign an int id

    /*
    |--------------------------------------------------------------------------
    | TIMESTAMPS
    |--------------------------------------------------------------------------
    | Only `created_at` exists — audit records are immutable.
    | We disable Eloquent's built-in timestamp management (which would look for
    | both created_at AND updated_at) and cast created_at manually in $casts.
    */
    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    | `id` is included in $fillable because AuditService generates the UUID
    | and passes it explicitly — Eloquent must not try to generate it.
    */

    /** @var list<string> */
    protected $fillable = [
        'id',
        'type',
        'reason',
        'who',
        'diff',
        // created_at intentionally excluded: set by DB DEFAULT current_timestamp()
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    | `diff → array`: auto JSON encode/decode matches the legacy pattern of
    |   storing the diff as a JSON string and decoding before returning.
    */

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'diff'       => 'array',     // { field: { before, after } }
            'created_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES  (used by AuditTrailController::index)
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: filter by exact `type` value.
     *
     * @param  Builder<AuditTrail>  $query
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: filter by exact `who` value.
     *
     * @param  Builder<AuditTrail>  $query
     */
    public function scopeByWho(Builder $query, string $who): Builder
    {
        return $query->where('who', $who);
    }

    /**
     * Scope: full-text search on `type` OR `who` (LIKE %q%).
     *
     * Legacy: WHERE type LIKE '%q%' OR who LIKE '%q%'
     *
     * @param  Builder<AuditTrail>  $query
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('type', 'LIKE', "%{$term}%")
              ->orWhere('who',  'LIKE', "%{$term}%");
        });
    }

    /**
     * Scope: filter by created_at >= $from.
     *
     * @param  Builder<AuditTrail>  $query
     */
    public function scopeFrom(Builder $query, string $from): Builder
    {
        return $query->where('created_at', '>=', $from);
    }

    /**
     * Scope: filter by created_at <= $to.
     *
     * @param  Builder<AuditTrail>  $query
     */
    public function scopeTo(Builder $query, string $to): Builder
    {
        return $query->where('created_at', '<=', $to);
    }
}
