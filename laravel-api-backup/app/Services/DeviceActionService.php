<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeviceAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * DeviceActionService
 *
 * Handles the full work-order lifecycle from backend/backend.php:
 *   create → pending → approved / rejected → completed
 *
 * Every mutation is:
 *   1. Wrapped in DB::transaction()
 *   2. Audited via AuditService::log()
 *   3. Guarded by business rules (self-approval prevention, status locks)
 */
final class DeviceActionService
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new action in 'pending' status.
     *
     * Priority is set to MAX(priority)+1 for the device's existing actions,
     * placing this action at the bottom of the board.
     * Legacy mirror: ?action=action_create
     *
     * @param  array<string, mixed>  $data  Validated from CreateDeviceActionRequest
     */
    public function create(array $data, string $who): DeviceAction
    {
        return DB::transaction(function () use ($data, $who): DeviceAction {
            $nextPriority = (DeviceAction::where('device_id', $data['device_id'])->max('priority') ?? 0) + 1;

            $action = DeviceAction::create([
                'device_id'  => $data['device_id'],
                'action'     => $data['action'],
                'details'    => $data['details'] ?? null,
                'status'     => DeviceAction::STATUS_PENDING,
                'priority'   => $nextPriority,
                'created_by' => $who,
            ]);

            // Audit: before = empty (new record)
            $this->audit->log('action_create', null, [], $action->toArray(), $who);

            return $action->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    /**
     * Update action fields — only creator or admin may edit.
     * Cannot edit when status is 'approved' (locked).
     *
     * Legacy mirror: ?action=action_update
     *
     * @param  array<string, mixed>  $data
     * @throws ModelNotFoundException   if not found
     * @throws AuthorizationException   if not creator/admin, or action is locked
     */
    public function update(int $id, array $data, string $who, bool $isAdmin = false): DeviceAction
    {
        $action = DeviceAction::findOrFail($id);

        if (! $isAdmin && ! $action->isOwnedBy($who)) {
            throw new AuthorizationException('Only the creator or an admin can edit this action.');
        }

        if ($action->isApproved()) {
            throw new AuthorizationException('Cannot edit an approved action.');
        }

        return DB::transaction(function () use ($action, $data, $who): DeviceAction {
            $before = $action->toArray();
            $action->update(array_filter([
                'action'   => $data['action'] ?? null,
                'details'  => $data['details'] ?? null,
                'priority' => isset($data['priority']) ? (int) $data['priority'] : null,
            ], fn ($v) => $v !== null));
            $after = $action->fresh()->toArray();

            $this->audit->log('action_update', null, $before, $after, $who);

            return $action->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    /**
     * Delete an action — only creator or admin may delete.
     * Cannot delete when status is 'approved'.
     *
     * Legacy mirror: ?action=action_delete
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function delete(int $id, string $who, bool $isAdmin = false): void
    {
        $action = DeviceAction::findOrFail($id);

        if (! $isAdmin && ! $action->isOwnedBy($who)) {
            throw new AuthorizationException('Only the creator or an admin can delete this action.');
        }

        if ($action->isApproved()) {
            throw new AuthorizationException('Cannot delete an approved action.');
        }

        DB::transaction(function () use ($action, $who): void {
            $snapshot = $action->toArray();
            $action->delete();
            $this->audit->log('action_delete', null, $snapshot, [], $who);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | APPROVE
    |--------------------------------------------------------------------------
    */

    /**
     * Approve a pending action — admin only, cannot approve own action.
     *
     * Legacy mirror: ?action=approve_action
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException  if self-approving or action not pending
     */
    public function approve(int $id, string $who): void
    {
        $action = DeviceAction::findOrFail($id);

        // Self-approval guard — mirrors legacy: if ($action['created_by'] === $WHO) abort
        if ($action->isCreatedBy($who)) {
            throw new AuthorizationException('Cannot approve your own action.');
        }

        if (! $action->isPending()) {
            throw new AuthorizationException("Action is already '{$action->status}'.");
        }

        DB::transaction(function () use ($action, $who): void {
            $before = $action->toArray();
            $action->update([
                'status'      => DeviceAction::STATUS_APPROVED,
                'approved_by' => $who,
                'approved_at' => now(),
            ]);
            $this->audit->log('action_approve', null, $before, $action->fresh()->toArray(), $who);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | REJECT
    |--------------------------------------------------------------------------
    */

    /**
     * Reject a pending action with a mandatory reason — admin only.
     * Cannot reject own action.
     *
     * Legacy mirror: ?action=reject_action
     *
     * @throws ModelNotFoundException
     * @throws AuthorizationException
     */
    public function reject(int $id, string $who, string $reason): void
    {
        $action = DeviceAction::findOrFail($id);

        if ($action->isCreatedBy($who)) {
            throw new AuthorizationException('Cannot reject your own action.');
        }

        if (! $action->isPending()) {
            throw new AuthorizationException("Action is already '{$action->status}'.");
        }

        DB::transaction(function () use ($action, $who, $reason): void {
            $before = $action->toArray();
            $action->update([
                'status' => DeviceAction::STATUS_REJECTED,
                'notes'  => $reason,
            ]);
            $this->audit->log('action_reject', $reason, $before, $action->fresh()->toArray(), $who);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ORDER (batch priority reorder)
    |--------------------------------------------------------------------------
    */

    /**
     * Batch-update priority for multiple actions in a single transaction.
     * Used by the drag-and-drop Kanban board.
     *
     * Legacy mirror: ?action=update_plan_order
     *
     * @param  list<array{id:int, priority:int}>  $items
     */
    public function updateOrder(array $items): void
    {
        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                DeviceAction::where('id', (int) $item['id'])
                            ->update(['priority' => (int) $item['priority']]);
            }
        });
    }
}
