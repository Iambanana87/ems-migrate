<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LiveDeviceData Eloquent Model
 *
 * Pre-computed live metrics cache — 1:1 with Device.
 * Written by the IoT data pipeline on every device heartbeat.
 * Read by DeviceController::live() for fast dashboard renders.
 *
 * Non-standard settings:
 *   - PK = device_id (string), not auto-increment
 *   - No Eloquent timestamps (last_updated is DB-managed via ON UPDATE)
 *
 * @property string          $device_id
 * @property string|null     $display_type
 * @property array|null      $live_data   Pre-computed metrics (decoded from JSON)
 * @property \Carbon\Carbon  $last_updated
 */
class LiveDeviceData extends Model
{
    use HasFactory;

    protected $table       = 'live_device_data';
    protected $primaryKey  = 'device_id';
    protected $keyType     = 'string';
    public    $incrementing = false;
    public    $timestamps  = false; // last_updated is managed by DB ON UPDATE trigger

    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'display_type',
        'live_data',
    ];
    // last_updated intentionally excluded — set by DB DEFAULT/ON UPDATE

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'live_data'    => 'array',   // JSON → PHP array auto-decode
            'last_updated' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id', 'device_id');
    }
}
