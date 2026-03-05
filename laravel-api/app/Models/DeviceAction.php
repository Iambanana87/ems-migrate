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
 * @property string          $title         Human-readable label
 * @property string|null     $details       Full description
 * @property string          $status        pending|approved|rejected|completed
 * @property int             $priority      Display order (lower = higher priority)
 * @property string|null     $created_by_name JWT username of creator
 * @property string|null     $approved_by_name JWT username of approver
 * @property \Carbon\Carbon|null $approved_at
 * @property string|null     $approval_note  Rejection reason/notes
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
    public const STATUS_OPEN        = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE        = 'done';
    public const STATUS_CANCELLED   = 'cancelled';

    public const APPROVAL_PENDING  = 'pending';
    public const APPROVAL_APPROVED = 'approved';
    public const APPROVAL_REJECTED = 'rejected';

    /** @var list<string> */
    protected $fillable = [
        'device_id',
        'title',
        'short_form',
        'status',
        'priority',
        'created_by_name',
        'created_by_user_id',
        'approved_by_name',
        'approved_at',
        'approval_note',
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
        return $this->approval_status === self::APPROVAL_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::APPROVAL_APPROVED;
    }

    /**
     * Check if the given user is the creator of this action.
     */
    public function isOwnedBy(string $who): bool
    {
        return $this->created_by_name === $who;
    }

    /**
     * Check if the given user created this action (blocks self-approval).
     */
    public function isCreatedBy(string $who): bool
    {
        return $this->created_by_name === $who;
    }
}
