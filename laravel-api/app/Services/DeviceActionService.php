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

    /*
    |--------------------------------------------------------------------------
    | PUBLIC CREATE
    |--------------------------------------------------------------------------
    */

    public function createPublic(array $data, int $creatorId, string $creatorName): array
    {
        $sf = json_decode($data['short_form'], true);
        if (!is_array($sf)) {
            throw new \Exception('Missing/invalid fields (device_id/title/short_form)', 400);
        }

        $plans = [];
        foreach ($sf as $x) {
            $label = strtolower(trim((string) ($x['label'] ?? '')));
            $val   = trim((string) ($x['value'] ?? ''));
            if ($val === '') continue;

            $isPlan = (strpos($label, 'action plan') !== false) || (strpos($label, 'plan #') === 0);
            if (!$isPlan) continue;

            $est = null;
            if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', (string) ($x['label'] ?? ''), $m)) {
                $est = $m[1];
            } elseif (!empty($x['est'])) {
                $est = trim((string) $x['est']);
            }

            $ownerName = isset($x['owner']) && trim((string) $x['owner']) !== '' ? trim((string) $x['owner']) : null;
            $ownerId   = isset($x['owner_user_id']) && $x['owner_user_id'] !== '' ? (int)$x['owner_user_id'] : null;

            $plans[] = [
                'plan_text'     => $val,
                'est_date'      => $est ?: null,
                'issue_note'    => isset($x['issue']) && strlen(trim((string) $x['issue'])) ? trim((string) $x['issue']) : null,
                'owner_user_id' => $ownerId,
                'owner_name'    => $ownerName,
            ];
        }

        // Structural Hardening constraint: DB::transaction explicit block
        return DB::transaction(function () use ($data, $sf, $creatorId, $creatorName, $plans): array {
            $issueType = $data['issue_type'] ?? '';
            $issueType = $issueType !== '' ? $issueType : null;

            $actionId = DB::table('device_actions')->insertGetId([
                'device_id'          => $data['device_id'],
                'title'              => $data['title'],
                'issue_type'         => $issueType,
                'short_form'         => json_encode($sf, \JSON_UNESCAPED_UNICODE),
                'status'             => 'open',
                'priority'           => 'medium',
                'created_by_user_id' => $creatorId,
                'created_by_name'    => $creatorName,
                'approval_status'    => 'pending',
                'created_at'         => now(),
            ]);

            $actionCode = sprintf('ISS%05d', $actionId);
            DB::table('device_actions')
                ->where('id', $actionId)
                ->update(['action_code' => $actionCode]);

            $createdPlans = [];
            foreach ($plans as $p) {
                // Prevent implicit data corruption: foreign key constraint validation inside transaction
                if ($p['owner_user_id'] !== null) {
                    $userExists = DB::table('users')->where('id', $p['owner_user_id'])->exists();
                    if (!$userExists) {
                        throw new \Exception("Integrity Check Failed: Invalid owner_user_id -> " . $p['owner_user_id'], 400);
                    }
                }

                $pid = DB::table('device_action_plans')->insertGetId([
                    'action_id'     => $actionId,
                    'action_code'   => $actionCode,
                    'plan_text'     => $p['plan_text'],
                    'est_date'      => $p['est_date'],
                    'issue_note'    => $p['issue_note'],
                    'owner_user_id' => $p['owner_user_id'],
                    'owner_name'    => $p['owner_name'],
                ]);

                $pcode = sprintf('AP%05d', $pid);
                DB::table('device_action_plans')
                    ->where('id', $pid)
                    ->update(['plan_code' => $pcode]);

                $createdPlans[] = [
                    'id'            => $pid,
                    'plan_code'     => $pcode,
                    'plan_text'     => $p['plan_text'],
                    'est_date'      => $p['est_date'],
                    'issue_note'    => $p['issue_note'],
                    'owner_user_id' => $p['owner_user_id'],
                    'owner_name'    => $p['owner_name'] ?? null,
                ];
            }

            // GỘP PLANS LUÔN Ở ĐÂY (Mirror Legacy Audit logic precisely)
            $after = [
                'id'                 => $actionId,
                'action_code'        => $actionCode,
                'device_id'          => $data['device_id'],
                'title'              => $data['title'],
                'issue_type'         => $issueType,
                'short_form'         => $sf,
                'status'             => 'open',
                'priority'           => 'medium',
                'created_by_user_id' => $creatorId,
                'created_by_name'    => $creatorName,
                'approval_status'    => 'pending',
                'plans'              => array_map(function($p) {
                    return [
                        'plan_code'  => $p['plan_code']  ?? null,
                        'plan_text'  => $p['plan_text']  ?? '',
                        'est_date'   => $p['est_date']   ?? null,
                        'owner_name' => $p['owner_name'] ?? null,
                    ];
                }, $createdPlans),
            ];

            $this->audit->log('create', 'device_actions:create_public#'.$actionId, [], $after, $creatorName);

            return [
                'id'          => $actionId,
                'action_code' => $actionCode,
                'plans'       => $createdPlans
            ];
        });
    }

    /*
    |--------------------------------------------------------------------------
    | PUBLIC READ
    |--------------------------------------------------------------------------
    */

    public function listDeviceActionsV2(array $data): array
    {
        // NO transaction block for READ endpoint (Phase 2 constraint)
        $sql = "
          SELECT
            a.id,
            a.device_id,
            a.title,
            a.issue_type,
            JSON_UNQUOTE(JSON_EXTRACT(a.short_form, '$[0].value')) AS `desc`,
            a.created_at,
            a.created_by_name,
            a.approval_status,
            a.status,
            c.plans_done,
            c.plans_total,
            DATE(c.max_est_date) AS planned_completion_date,
            CASE
              WHEN c.plans_total IS NULL OR c.plans_total = 0 THEN a.status
              WHEN c.plans_done = 0 THEN 'open'
              WHEN c.plans_done < c.plans_total THEN 'in_progress'
              ELSE 'done'
            END AS status_auto
          FROM device_actions a
          LEFT JOIN (
            SELECT
              action_id,
              SUM(status = 'done') AS plans_done,
              COUNT(*)             AS plans_total,
              MAX(est_date)        AS max_est_date
            FROM device_action_plans
            GROUP BY action_id
          ) c ON c.action_id = a.id
          WHERE a.device_id = ?
          ORDER BY
            (CASE WHEN c.plans_total IS NULL OR c.plans_done < c.plans_total THEN 0 ELSE 1 END),
            c.max_est_date IS NULL,
            c.max_est_date ASC,
            a.id DESC
        ";

        $rawRows = DB::select($sql, [$data['device_id']]);
        
        $rows = array_map(function ($row) {
            return (array) $row;
        }, $rawRows);

        return [
            'device_id' => $data['device_id'],
            'count'     => count($rows),
            'items'     => $rows,
        ];
    }
}
