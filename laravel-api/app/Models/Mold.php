<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Mold Eloquent Model
 *
 * IoT data records from injection-mold machines.
 * Written by device firmware; read by DeviceService for efficiency calculations.
 *
 * @property int             $id
 * @property \Carbon\Carbon  $datetime    Timestamp from device
 * @property string          $uuid        Dedup key
 * @property string|null     $device_id
 * @property string|null     $product
 * @property int|null        $cavities
 * @property float|null      $cycle_time  Seconds per cycle
 */
class Mold extends Model
{
    use HasFactory;

    protected $table      = 'mold';
    public    $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'datetime', 'uuid', 'device_id', 'product', 'cavities', 'cycle_time',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'         => 'integer',
            'datetime'   => 'datetime',
            'cavities'   => 'integer',
            'cycle_time' => 'float',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Latest N records for a device — used for rolling efficiency calculation.
     *
     * @param  Builder<Mold>  $query
     */
    public function scopeLatestForDevice(Builder $query, string $deviceId, int $limit): Builder
    {
        return $query
            ->where('device_id', $deviceId)
            ->orderByDesc('datetime')
            ->limit($limit);
    }

    /**
     * Date-range filter for m=history search.
     *
     * @param  Builder<Mold>  $query
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
