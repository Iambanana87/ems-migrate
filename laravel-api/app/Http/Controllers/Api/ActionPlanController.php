<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\DeviceAction\UpdatePlanOrderRequest;
use App\Services\DeviceActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * ActionPlanController
 *
 * Handles Kanban board ordering updates.
 *
 * c=ActionPlan & m=updateOrder → updateOrder() [admin]
 *
 * Legacy mirror: ?action=update_plan_order in backend/backend.php
 */
class ActionPlanController extends Controller
{
    use RequiresAuth;

    public function __construct(
        private readonly DeviceActionService $actionService,
    ) {}

    /**
     * POST /api/gateway?c=ActionPlan&m=updateOrder
     *
     * Body: { "items": [{ "id": 1, "priority": 0 }, { "id": 3, "priority": 1 }] }
     *
     * All priority updates are applied atomically in a single transaction.
     */
    public function updateOrder(UpdatePlanOrderRequest $request): JsonResponse
    {
        $this->requireRole($request, ['admin']);

        $this->actionService->updateOrder($request->getItems());

        return response()->json(['status' => 'ok', 'message' => 'Order saved.']);
    }
}
