<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceStatus Eloquent Model
 *
 * Live connection/threshold status — 1:1 with Device.
 *
 * Non-standard Eloquent settings:
 *   - PK = device_id (string), not auto-increment integer
 *   - No updated_at / created_at columns
 *
 * @property string          $device_id
 * @property string          $connection_status   'Connected' | 'Disconnected'
 * @property string          $threshold_status    'Normal' | 'Breached'
 * @property \Carbon\Carbon|null $last_heartbeat
 * @property \Carbon\Carbon|null $last_disconnect_notification
 * @property \Carbon\Carbon|null $last_threshold_notification
 */
class DeviceStatus extends Model
{
    use HasFactory;

    protected $table       = 'device_status';
    protected $primaryKey  = 'device_id';
    protected $keyType     = 'string';
    public    $incrementing = false;
    public    $timestamps  = false;

    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'last_heartbeat',
        'connection_status',
        'threshold_status',
        'last_disconnect_notification',
        'last_threshold_notification',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'last_heartbeat'                => 'datetime',
            'last_disconnect_notification'  => 'datetime',
            'last_threshold_notification'   => 'datetime',
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

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isConnected(): bool
    {
        return $this->connection_status === 'Connected';
    }

    public function isBreached(): bool
    {
        return $this->threshold_status === 'Breached';
    }
}
