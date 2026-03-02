<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Tuft Eloquent Model
 *
 * IoT data records from tufting machines.
 * `output` = pcs produced per reporting interval.
 *
 * @property int             $id
 * @property \Carbon\Carbon  $datetime
 * @property string          $uuid
 * @property string|null     $device_id
 * @property string|null     $product
 * @property string|null     $process
 * @property int|null        $output    Pieces produced
 */
class Tuft extends Model
{
    use HasFactory;

    protected $table      = 'tuft';
    public    $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'datetime', 'uuid', 'device_id', 'product', 'process', 'output',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'       => 'integer',
            'datetime' => 'datetime',
            'output'   => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Latest N records for rolling efficiency window.
     *
     * @param  Builder<Tuft>  $query
     */
    public function scopeLatestForDevice(Builder $query, string $deviceId, int $limit): Builder
    {
        return $query
            ->where('device_id', $deviceId)
            ->orderByDesc('datetime')
            ->limit($limit);
    }

    /**
     * Date-range filter for history endpoint.
     *
     * @param  Builder<Tuft>  $query
     */
    public function scopeInDateRange(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null) {
            $query->where('datetime', '>=', $from);
        }
        if ($to !== null) {
            $query->where('datetime', '<=', $to);
        }
        return $query;
    }
}
