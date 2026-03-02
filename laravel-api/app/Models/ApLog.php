<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ApLog Eloquent Model
 *
 * Represents a single row in the `ap_logs` table in the "central" database.
 * Written by IoT devices (ESP32) via log_data.php.
 * Read by ApStatusService to compute the latest status per Access Point.
 *
 * @property int             $id
 * @property string          $ap_name
 * @property string|null     $location
 * @property string          $status      Raw status string (e.g. ONLINE, OFFLINE)
 * @property float|null      $ping_ms
 * @property \Carbon\Carbon  $logged_at
 */
class ApLog extends Model
{
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | CONNECTION — "central" database, NOT the default "production"
    |--------------------------------------------------------------------------
    | Maps to the 'central' entry in config/database.php connections array.
    | DB credentials: DB_CENTRAL_HOST, DB_CENTRAL_DATABASE, etc.
    */
    protected $connection = 'central';

    protected $table = 'ap_logs';

    /*
    |--------------------------------------------------------------------------
    | TIMESTAMPS
    |--------------------------------------------------------------------------
    | The ap_logs table uses `logged_at` instead of the Eloquent convention
    | of created_at / updated_at. We disable automatic timestamps and declare
    | the custom column as a cast so Carbon handles it correctly.
    */
    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    | Used by log_data.php equivalent (ApLogController::store in future batch).
    */

    /** @var list<string> */
    protected $fillable = [
        'ap_name',
        'location',
        'status',
        'ping_ms',
    ];

    // logged_at is set by DB DEFAULT current_timestamp() on insert.

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'        => 'integer',
            'ping_ms'   => 'float',
            'logged_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: latest record per AP (used by ApStatusService).
     *
     * Produces the subquery:
     *   SELECT ap_name, MAX(logged_at) AS max_logged_at
     *   FROM ap_logs GROUP BY ap_name
     *
     * Usage:
     *   ApLog::latestPerAp()->get()
     *
     * @param  Builder<ApLog>  $query
     * @return Builder<ApLog>
     */
    public function scopeLatestPerAp(Builder $query): Builder
    {
        // Subquery: latest logged_at per ap_name
        $sub = self::query()
            ->selectRaw('ap_name, MAX(logged_at) AS max_logged_at')
            ->groupBy('ap_name');

        return $query
            ->select('ap_logs.*')
            ->joinSub($sub, 'latest', function ($join): void {
                $join->on('ap_logs.ap_name', '=', 'latest.ap_name')
                     ->on('ap_logs.logged_at', '=', 'latest.max_logged_at');
            });
    }

    /**
     * Scope: filter to a specific list of AP names.
     *
     * @param  Builder<ApLog>  $query
     * @param  list<string>    $names
     * @return Builder<ApLog>
     */
    public function scopeWithNames(Builder $query, array $names): Builder
    {
        if (empty($names)) {
            return $query;
        }

        return $query->whereIn('ap_logs.ap_name', $names);
    }
}
