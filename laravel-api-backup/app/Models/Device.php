<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Device Eloquent Model
 *
 * @property int        $id
 * @property string     $device_id        Business key (unique)
 * @property string     $display_type     'mold' | 'tuft' | 'blister'
 * @property string|null $process         Product family / process label
 * @property string|null $product
 * @property float|null  $target_limit    Efficiency / cycle-time target
 * @property float|null  $lower_limit
 * @property float|null  $upper_limit
 * @property float|null  $efficiency_lower_limit
 * @property float|null  $efficiency_upper_limit
 * @property int|null    $cavities        Mold: number of cavities
 * @property int|null    $brushes_per_cycle Blister: brushes per cycle
 * @property int|null    $hole_per_brush  Tuft: holes per brush
 * @property int|null    $capacity        Pre-computed capacity override
 * @property int|null    $history_count   Number of records used for rolling stats
 * @property bool        $flex            Multi-product / flexible flag
 */
class Device extends Model
{
    use HasFactory;

    protected $table      = 'devices';
    public    $timestamps = false; // No created_at / updated_at in legacy schema

    /** @var list<string> */
    protected $fillable = [
        'device_id', 'display_type', 'data_source', 'product', 'model',
        'manufacturer', 'manufacturing_date', 'cavities', 'hole_per_brush',
        'process', 'metric_name', 'lower_limit', 'target_limit', 'upper_limit',
        'frequency', 'freq_check_limit', 'capacity', 'mold_type',
        'efficiency_upper_limit', 'efficiency_lower_limit', 'flex',
        'brushes_per_cycle', 'total_count', 'unit', 'total_count_updated_at',
        'cavity_count', 'cavity_count_updated_at', 'total_rpm',
        'total_rpm_updated_at', 'total_cycle', 'total_cycle_updated_at',
        'history_count', 'history_count_updated_at', 'client',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'                    => 'integer',
            'cavities'              => 'integer',
            'hole_per_brush'        => 'integer',
            'frequency'             => 'integer',
            'freq_check_limit'      => 'integer',
            'capacity'              => 'integer',
            'history_count'         => 'integer',
            'brushes_per_cycle'     => 'integer',
            'flex'                  => 'boolean',
            'target_limit'          => 'float',
            'lower_limit'           => 'float',
            'upper_limit'           => 'float',
            'efficiency_lower_limit' => 'float',
            'efficiency_upper_limit' => 'float',
            'manufacturing_date'    => 'date',
            'total_count_updated_at'    => 'datetime',
            'cavity_count_updated_at'   => 'datetime',
            'total_rpm_updated_at'      => 'datetime',
            'total_cycle_updated_at'    => 'datetime',
            'history_count_updated_at'  => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    /** Live connection & threshold status (1:1) */
    public function status(): HasOne
    {
        return $this->hasOne(DeviceStatus::class, 'device_id', 'device_id');
    }

    /** Pre-computed live data cache (1:1) */
    public function liveData(): HasOne
    {
        return $this->hasOne(LiveDeviceData::class, 'device_id', 'device_id');
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: filter by display_type.
     *
     * @param  Builder<Device>  $query
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('display_type', $type);
    }

    /**
     * Scope: eager-load status + liveData for the dashboard feed.
     *
     * @param  Builder<Device>  $query
     */
    public function scopeWithLiveFeed(Builder $query): Builder
    {
        return $query->with(['status', 'liveData']);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /** Whether this device uses mold IoT records */
    public function isMold(): bool    { return $this->display_type === 'mold'; }
    public function isTuft(): bool    { return $this->display_type === 'tuft'; }
    public function isBlister(): bool { return $this->display_type === 'blister'; }
}
