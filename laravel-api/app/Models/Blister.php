<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Blister Eloquent Model
 *
 * IoT data records from blister-packaging machines.
 *
 * ⚠️ LEGACY TYPE QUIRKS:
 *   - BrushesperCycle: stored as varchar — may be 'N/A' or a numeric string.
 *     DeviceService casts to int at compute time, defaulting to 1 if non-numeric.
 *   - output: stored as varchar — may be non-numeric during error states.
 *     DeviceService casts to int, defaulting to 0 if non-numeric.
 *
 * Column name `BrushesperCycle` (mixed case) is intentional — preserved exactly
 * from the legacy schema to avoid a destructive migration rename.
 *
 * @property int             $id
 * @property \Carbon\Carbon|null $datetime
 * @property string          $uuid
 * @property string          $device_id
 * @property string          $product
 * @property string          $BrushesperCycle  May be 'N/A'
 * @property int             $cyclecount
 * @property string|null     $output           May be non-numeric
 */
class Blister extends Model
{
    use HasFactory;

    protected $table      = 'blister';
    public    $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'datetime', 'uuid', 'device_id', 'product',
        'BrushesperCycle', 'cyclecount', 'output',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'        => 'integer',
            'datetime'  => 'datetime',
            'cyclecount' => 'integer',
            // BrushesperCycle and output intentionally left as strings —
            // they may contain 'N/A'. DeviceService handles numeric coercion.
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
     * @param  Builder<Blister>  $query
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
     * @param  Builder<Blister>  $query
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

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Safe numeric accessor for BrushesperCycle — returns 1 when 'N/A'.
     * Used by DeviceService::computeCapacity() and computeEfficiency().
     */
    public function getBrushCount(): int
    {
        $v = $this->BrushesperCycle;
        return is_numeric($v) ? (int) $v : 1;
    }

    /**
     * Safe numeric accessor for output — returns 0 when non-numeric.
     */
    public function getOutputCount(): int
    {
        $v = $this->output;
        return is_numeric($v) ? (int) $v : 0;
    }
}
