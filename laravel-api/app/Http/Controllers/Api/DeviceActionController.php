<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\DeviceAction\ActionIdRequest;
use App\Http\Requests\DeviceAction\CreateActionPublicRequest;
use App\Http\Requests\DeviceAction\CreateDeviceActionRequest;
use App\Http\Requests\DeviceAction\ListActionPlansRequest;
use App\Http\Requests\DeviceAction\ListDeviceActionsV2Request;
use App\Http\Requests\DeviceAction\RejectActionRequest;
use App\Http\Requests\DeviceAction\UpdateActionPlanStatusRequest;
use App\Http\Requests\DeviceAction\UpdateDeviceActionRequest;
use App\Services\DeviceActionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

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
        $claims = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $who    = $this->whoFromClaims($claims);

        $action = $this->actionService->create($request->validated(), $who);

        return response()->json(['status' => 'ok', 'message' => 'Action created.', 'data' => $action->toArray()]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=update — Edit action (creator or admin)
    |--------------------------------------------------------------------------
    */

    public function update(UpdateDeviceActionRequest $request): JsonResponse
    {
        $claims  = $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));
        $who     = $this->whoFromClaims($claims);
        $isAdmin = strtolower($claims['role'] ?? '') === 'admin';

        try {
            $action = $this->actionService->update(
                id:      $request->getId(),
                data:    $request->validated(),
                who:     $who,
                isAdmin: $isAdmin,
            );
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Action not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'message' => 'Action updated.', 'data' => $action->toArray()]);
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
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Action not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'message' => 'Action deleted.']);
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
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Action not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'message' => 'Approved.']);
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
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Action not found.'], 404);
        } catch (AuthorizationException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'message' => 'Rejected.']);
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
            // Read endpoints bypass Auth roles in legacy block natively ("// if (!$AUTH)...")
            $data = $this->actionService->listDeviceActionsV2($request->validated());
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
}
