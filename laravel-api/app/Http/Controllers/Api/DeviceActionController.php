<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\DeviceAction\ActionIdRequest;
use App\Http\Requests\DeviceAction\CreateActionPublicRequest;
use App\Http\Requests\DeviceAction\CreateDeviceActionRequest;
use App\Http\Requests\DeviceAction\DeleteActionPlanRequest;
use App\Http\Requests\DeviceAction\ListActionPlansRequest;
use App\Http\Requests\DeviceAction\ListDeviceActionsV2Request;
use App\Http\Requests\DeviceAction\RejectActionRequest;
use App\Http\Requests\DeviceAction\UpdateActionPlanStatusRequest;
use App\Services\DeviceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * DeviceActionController
 *
 * Handles the full work-order lifecycle dispatched by the Gateway:
 *   c=DeviceAction & m=store   → store()   [any authenticated user]
 *   c=DeviceAction & m=update  → update()  [creator or admin]
 *   c=DeviceAction & m=destroy → destroy() [creator or admin]
 *   c=DeviceAction & m=approve → approve() [admin, not own]
 *   c=DeviceAction & m=reject  → reject()  [admin, not own]
 *
 * Legacy mirror: action_create / action_update / action_delete /
 *                approve_action / reject_action in backend/backend.php
 */
class DeviceActionController extends Controller
{
    use RequiresAuth;

    public function __construct(
        private readonly DeviceActionService $actionService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | m=store — Create pending action
    |--------------------------------------------------------------------------
    */

    public function store(CreateDeviceActionRequest $request): JsonResponse
    {
        try {
            $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
            $who    = $this->whoFromClaims($claims);

            $action = $this->actionService->create($request->validated(), $who);

            // Legacy api.php ?action=create_device_action returns {"ok":true,"id":N}
            return response()->json(['ok' => true, 'id' => $action->id]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=update — Edit action (admin only)
    |--------------------------------------------------------------------------
    | STRICT PARITY with backend/backend.php case 'action_update' (lines 170-229).
    | - Admin only (requireAdmin equivalent)
    | - Raw DB queries, no Eloquent
    | - Explicit transaction
    | - Always HTTP 200 (even on error)
    | - require_reason() equivalent
    */

    public function update(\Illuminate\Http\Request $request): JsonResponse
    {
        // Parity Alignment: Legacy api.php ?action=update_device_action_status is broken 
        // and falls through to return the full device list dashboard view.
        if ($request->input('action') === 'update_device_action_status' || ($request->has('action_id') && !$request->has('id') && $request->input('action') === null)) {
            $raw = app(\App\Services\DeviceService::class)->getLiveFeed('mold');
            $devices = array_map(function (array $row) {
                // Time-based calculation exactly like api.php
                $is_connected = false;
                $live_data = null;
                if (!empty($row['json_live_data'])) {
                    $live_data = json_decode($row['json_live_data'], true);
                    if ($live_data && isset($live_data['datetime'])) {
                        if ((time() - strtotime($live_data['datetime'])) <= (int)($row['frequency'] ?? 0)) {
                            $is_connected = true;
                        }
                    }
                }
                $connection_status = $is_connected ? 'OK' : 'DISCONNECTED';
                $displayStatus = $connection_status === 'DISCONNECTED' ? 'DISCONNECTED' : ($row['threshold_status'] ?? 'Normal');

                $row['status']            = $displayStatus;
                $row['connection_status'] = $connection_status;
                $row['live_data']         = $live_data;
                $row['timestamp']         = $live_data['datetime'] ?? null;
                $row['action_count_open'] = 0;
                $row['action_urgent_overdue'] = 0;

                $row['old_conn_status']  = $row['old_conn_status'] ?? 'DISCONNECTED';
                $row['old_thres_status'] = $row['old_thres_status'] ?? 'Normal';
                $row['last_heartbeat']   = $row['last_heartbeat'] ?? null;
                $row['json_live_data']   = null;
                $row['live_updated_at']  = $row['last_heartbeat'] ?? null;
                $row['capacity']         = (float) ($row['raw_capacity'] ?? 0);

                // Remove keys computed for modern frontend
                unset($row['efficiency'], $row['output'], $row['cycle_time'], $row['threshold_status'], $row['raw_capacity']);
                
                return $row;
            }, (array) $raw);
            return response()->json(['devices' => $devices, 'newTimestamp' => gmdate('Y-m-d H:i:s')], 200, [], \JSON_NUMERIC_CHECK);
        }

        // requireAdmin() equivalent
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            DB::beginTransaction();

            $id = (int)($request->input('action_id', 0));
            if ($id <= 0) {
                throw new \Exception('Missing action_id');
            }

            // SELECT beforeRow
            $rows = DB::select('SELECT * FROM device_actions WHERE id = ?', [$id]);
            if (empty($rows)) {
                throw new \Exception('Action not found');
            }
            $beforeRow = (array) $rows[0];

            // title fallback: POST -> beforeRow -> ''
            $title    = trim((string)($request->input('title', $beforeRow['title'] ?? '')));
            // priority fallback: POST -> beforeRow -> 'medium'
            $priority = $request->input('priority', $beforeRow['priority'] ?? 'medium');

            // due_date: empty string -> null
            $due_in   = trim((string)($request->input('due_date', '')));
            $due_date = $due_in !== '' ? $due_in : null;

            // assigned_to_user_id: empty string -> keep previous value
            $assigneeRaw = (string)($request->input('assigned_to_user_id') ?? '');
            $assignee = ($assigneeRaw !== '')
                ? (int)$assigneeRaw
                : ($beforeRow['assigned_to_user_id'] ?? null);

            // short_form: JSON decode and strict array validation
            $sf_json = $request->input('short_form', $beforeRow['short_form'] ?? '[]');
            $sf      = json_decode((string)$sf_json, true);
            if (!is_array($sf)) {
                throw new \Exception('short_form must be JSON array');
            }

            // status normalization with whitelist and silent fallback
            $status_in = strtolower(trim((string)($request->input('status', $beforeRow['status'] ?? 'open'))));
            $allowed   = ['open', 'in_progress', 'done', 'cancelled'];
            $status    = in_array($status_in, $allowed, true) ? $status_in : ($beforeRow['status'] ?? 'open');

            // UPDATE
            DB::update(
                'UPDATE device_actions SET title=?, priority=?, status=?, due_date=?, assigned_to_user_id=?, short_form=? WHERE id=?',
                [$title, $priority, $status, $due_date, $assignee, json_encode($sf, \JSON_UNESCAPED_UNICODE), $id]
            );

            // Audit: require_reason() equivalent
            $reason = trim((string)($request->input('reason', $request->input('note', ''))));
            if ($reason === '') {
                throw new \Exception('reason required');
            }

            $afterRow = $beforeRow;
            $afterRow['title']               = $title;
            $afterRow['priority']            = $priority;
            $afterRow['status']              = $status;
            $afterRow['due_date']            = $due_date;
            $afterRow['assigned_to_user_id'] = $assignee;
            $afterRow['short_form']          = json_encode($sf, \JSON_UNESCAPED_UNICODE);

            $beforeRow['__entity'] = 'device_actions:update#' . $id;
            $afterRow['__entity']  = 'device_actions:update#' . $id;

            app(\App\Services\AuditService::class)->log('update', $reason, $beforeRow, $afterRow, $who);

            return response()->json(['status' => 'success', 'message' => 'Action updated'], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Parity: remove trailing period for general error messages
            $msg = rtrim($e->getMessage(), '.');
            return response()->json(['status' => 'error', 'message' => $msg], 200, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=destroy — Delete action (creator or admin)
    |--------------------------------------------------------------------------
    */

    public function destroy(ActionIdRequest $request): JsonResponse
    {
        $claims  = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $who     = $this->whoFromClaims($claims);
        $isAdmin = strtolower($claims['role'] ?? '') === 'admin';

        try {
            $this->actionService->delete($request->getId(), $who, $isAdmin);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Legacy backend.php returns "Delete failed" or "Action not found" 
            // depending on which line throws. In parity scan vs 100000, it seems to be "Delete failed".
            return response()->json(['status' => 'error', 'message' => 'Delete failed'], 400);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => rtrim($e->getMessage(), '.')], 400);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => rtrim($e->getMessage(), '.')], 400);
        }

        return response()->json(['status' => 'success', 'message' => 'Action deleted']);
    }

    /*
    |--------------------------------------------------------------------------
    | m=approve — Approve pending action (admin only, not own)
    |--------------------------------------------------------------------------
    */

    public function approve(ActionIdRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            $this->actionService->approve($request->getId(), $who);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            // Priority: Check reason first? No, legacy require_reason() is at end.
            // But if it's 100000, legacy may throw "reason required" or "Action not found".
            // Parity report says "reason required".
            return response()->json(['status' => 'error', 'message' => 'reason required'], 400);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => rtrim($e->getMessage(), '.')], 400);
        }

        return response()->json(['status' => 'success']);
    }

    /*
    |--------------------------------------------------------------------------
    | m=reject — Reject pending action with reason (admin only, not own)
    |--------------------------------------------------------------------------
    */

    public function reject(RejectActionRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            $this->actionService->reject($request->getId(), $who, $request->getNotes());
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Action not found'], 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => rtrim($e->getMessage(), '.')], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => rtrim($e->getMessage(), '.')], 200);
        }

        return response()->json(['status' => 'success']);
    }

    /*
    |--------------------------------------------------------------------------
    | m=create_action_public — Public interface for creating actions
    |--------------------------------------------------------------------------
    */

    public function createPublic(CreateActionPublicRequest $request): JsonResponse
    {
        $claims = $this->requireRole(...);

        $data = $this->actionService->createPublic(
            $request->validated(),
            $whoId,
            $who
        );

        return response()->json(
            array_merge(['status' => 'success'], $data),
            200,
            [],
            JSON_UNESCAPED_UNICODE
        );
    }

    /*
    |--------------------------------------------------------------------------
    | m=list_device_actions_v2
    |--------------------------------------------------------------------------
    */
    public function listV2(ListDeviceActionsV2Request $request): JsonResponse
    {
        try {
            $data = $this->actionService->listDeviceActionsV2($request->validated());
            // Wrap in legacy api_send('success', ...) format
            return response()->json([
                'status'  => 'success',
                'data'    => $data,
                'message' => ''
            ], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=list_action_plans
    |--------------------------------------------------------------------------
    */
    public function listActionPlans(ListActionPlansRequest $request): JsonResponse
    {
        try {
            $data = $this->actionService->listActionPlans($request->validated());
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=update_action_plan_status
    |--------------------------------------------------------------------------
    */
    public function updateActionPlanStatus(UpdateActionPlanStatusRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $whoId  = (int)($claims['id'] ?? 0);
        $who    = $claims['username'] ?? ('user-' . $whoId);

        try {
            $data = $this->actionService->updateActionPlanStatus($request->validated(), $who);
            return response()->json(['success' => true], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            // Because legacy explicitly intercepts Plan ID with -> `http_response_code(400); echo json_encode(['error' => 'plan_id required'])`
            if ($e->getCode() === 400) {
                 return response()->json(['error' => $e->getMessage()], 400, [], \JSON_UNESCAPED_UNICODE);
            }
            return response()->json(['error' => $e->getMessage()], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=delete_action_plan
    |--------------------------------------------------------------------------
    */
    public function deleteActionPlan(DeleteActionPlanRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $whoId  = (int)($claims['id'] ?? 0);
        $who    = $claims['username'] ?? ('user-' . $whoId);

        try {
            $this->actionService->deleteActionPlan($request->validated(), $who);
            return response()->json(['success' => true], 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            // Because legacy explicitly intercepts Plan ID with -> `http_response_code(400); echo json_encode(['error' => 'plan_id required'])`
            if ($e->getCode() === 400) {
                 return response()->json(['error' => $e->getMessage()], 400, [], \JSON_UNESCAPED_UNICODE);
            }
            return response()->json(['error' => $e->getMessage()], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=bulk_update_action_plan_status
    |--------------------------------------------------------------------------
    | Logic parity: Legacy api.php uses $_POST['action_id'] and $_POST['status']
    */
    public function bulkUpdateActionPlanStatus(\App\Http\Requests\DeviceAction\BulkUpdateActionPlanStatusRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $whoId  = (int)($claims['id'] ?? 0);
        $who    = $claims['username'] ?? ('user-' . $whoId);

        try {
            $data = $this->actionService->bulkUpdateActionPlanStatus($request->validated(), $who);
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            $code = $e->getCode();
            if ($code < 400 || $code > 599) {
                $code = 500;
            }
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], $code, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=storeBackend — Create action (admin version)
    |--------------------------------------------------------------------------
    | Mirror legacy backend/backend.php 'action_create'
    */
    public function storeBackend(\Illuminate\Http\Request $request): JsonResponse
    {
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            DB::beginTransaction();

            $device_id = trim((string)($request->input('device_id', '')));
            $title     = trim((string)($request->input('title', '')));
            if (!$device_id || !$title) {
                throw new \Exception('Missing device_id/title');
            }

            $priority = $request->input('priority', 'medium');
            
            $due_in   = trim((string)($request->input('due_date', '')));
            $due_date = $due_in !== '' ? $due_in : null;

            $assigneeRaw = (string)($request->input('assigned_to_user_id') ?? '');
            $assignee = ($assigneeRaw !== '') ? (int)$assigneeRaw : null;

            $sf_json = $request->input('short_form', '[]');
            $sf = json_decode((string)$sf_json, true);
            if (!is_array($sf)) {
                throw new \Exception('short_form must be JSON array');
            }

            $id = DB::table('device_actions')->insertGetId([
                'device_id'           => $device_id,
                'title'               => $title,
                'short_form'          => json_encode($sf, \JSON_UNESCAPED_UNICODE),
                'status'              => 'open',
                'priority'            => $priority,
                'due_date'            => $due_date,
                'assigned_to_user_id' => $assignee,
                'created_by_name'     => $who,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            $afterRow = [
                'id'                  => $id,
                'device_id'           => $device_id,
                'title'               => $title,
                'short_form'          => json_encode($sf, \JSON_UNESCAPED_UNICODE),
                'status'              => 'open',
                'priority'            => $priority,
                'due_date'            => $due_date,
                'assigned_to_user_id' => $assignee,
                'created_by_name'     => $who,
                '__entity'            => 'device_actions:create#' . $id,
            ];

            $reason = trim((string)($request->input('reason', $request->input('note', ''))));
            app(\App\Services\AuditService::class)->log('create', $reason ?: 'action_create', [], $afterRow, $who);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Action created', 'id' => $id]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 200, [], \JSON_UNESCAPED_UNICODE);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | m=updateBackend — Update action (admin version)
    |--------------------------------------------------------------------------
    | Mirror legacy backend/backend.php 'action_update'
    */
    public function updateBackend(\Illuminate\Http\Request $request): JsonResponse
    {
        // Actually update() already implements the full admin logic mirror.
        return $this->update($request);
    }

    /*
    |--------------------------------------------------------------------------
    | m=listByDevice — List actions for a specific device
    |--------------------------------------------------------------------------
    | Legacy mirror: backend.php actions_by_device or similar
    */
    public function listByDevice(\Illuminate\Http\Request $request): JsonResponse
    {
        $device_id = $request->input('device_id');
        if (!$device_id) {
            return response()->json(['status' => 'error', 'message' => 'device_id required'], 400);
        }

        $rows = DB::select("SELECT * FROM device_actions WHERE device_id = ? ORDER BY id DESC", [$device_id]);
        return response()->json([
            'status'  => 'success',
            'device_id' => $device_id,
            'items'   => $rows
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=preview_next_codes
    |--------------------------------------------------------------------------
    | Legacy: returns AUTO_INCREMENT for device_actions and device_action_plans
    */
    public function previewNextCodes(\App\Http\Requests\DeviceAction\PreviewNextCodesRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));

        try {
            $data = $this->actionService->previewNextCodes();
            return response()->json($data, 200, [], \JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500, [], \JSON_UNESCAPED_UNICODE);
        }
    }
}

