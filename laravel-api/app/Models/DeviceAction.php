<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DeviceAction Eloquent Model
 *
 * Represents a work-order / maintenance action request for a device.
 * Powers the Kanban-style action board in the Vue frontend.
 *
 * Workflow:  pending → approved → completed
 *            pending → rejected  (terminal)
 *
 * @property int             $id
 * @property string          $device_id
 * @property string          $action        Human-readable label
 * @property string|null     $details       Full description
 * @property string          $status        pending|approved|rejected|completed
 * @property int             $priority      Display order (lower = higher priority)
 * @property string|null     $created_by    JWT username of creator
 * @property string|null     $approved_by   JWT username of approver
 * @property \Carbon\Carbon|null $approved_at
 * @property string|null     $notes         Rejection reason
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 */
class DeviceAction extends Model
{
    use HasFactory;

    protected $table = 'device_actions';

    /*
    |--------------------------------------------------------------------------
    | STATUS CONSTANTS
    |--------------------------------------------------------------------------
    | Used by DeviceActionService for all status transitions.
    | Keeps magic strings out of service/controller code.
    */
    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'action',
        'details',
        'status',
        'priority',
        'created_by',
        'approved_by',
        'approved_at',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'          => 'integer',
            'priority'    => 'integer',
            'approved_at' => 'datetime',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
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
    | STATUS SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * @param  Builder<DeviceAction>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * @param  Builder<DeviceAction>  $query
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * @param  Builder<DeviceAction>  $query
     */
    public function scopeForDevice(Builder $query, string $deviceId): Builder
    {
        return $query->where('device_id', $deviceId);
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS HELPERS
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the given user is the creator of this action.
     */
    public function isOwnedBy(string $who): bool
    {
        return $this->created_by === $who;
    }

    /**
     * Check if the given user created this action (blocks self-approval).
     */
    public function isCreatedBy(string $who): bool
    {
        return $this->created_by === $who;
    }
}
