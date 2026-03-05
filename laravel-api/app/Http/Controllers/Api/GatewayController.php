<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\ApStatusController;
use App\Http\Controllers\Api\AuditTrailController;
use App\Http\Controllers\Api\ActionPlanController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceActionController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\DeviceStatusController;
use App\Http\Controllers\Api\FactoryLayoutController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * GatewayController
 *
 * The single HTTP entry point for ALL legacy frontend requests.
 * Intercepts the ?c=<Controller>&m=<method> query parameters and
 * dispatches to the correct modern Laravel controller + method.
 *
 * ROUTING RULE:
 *   ANY /api/gateway?c=<Controller>&m=<method>
 *         ↓
 *   GatewayController::dispatch()
 *         ↓
 *   Resolved controller method (with automatic FormRequest / DI injection)
 *
 * HOW TO ADD NEW ROUTES:
 *   When a new legacy file is migrated, add its c/m pairs below in DISPATCH_MAP.
 *   Format: '<c>.<m>' => [ControllerClass::class, 'methodName']
 *
 * HOW FORMREQUESTS ARE INJECTED:
 *   `app()->call([$controller, $method])` uses Laravel's MethodBinder,
 *   which reflects on the method signature, resolves FormRequest dependencies,
 *   runs authorize() + validate(), and injects the validated request automatically.
 *   On validation failure, HttpResponseException is thrown and returns a 400 JSON.
 */
class GatewayController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | DISPATCH MAP
    |--------------------------------------------------------------------------
    | Key format: '<c_value>.<m_value>'  (case-sensitive, match legacy exactly)
    |
    | This map grows as each legacy PHP file is migrated through the phases.
    | Current batch: Auth (login.php + logout.php)
    |--------------------------------------------------------------------------
    */
    private const DISPATCH_MAP = [

        // ── Auth ─────────────────────────────────────────────────────────────
        // Legacy: backend/login.php  (POST), backend/logout.php (GET)
        'Auth.login'  => [AuthController::class, 'login'],
        'Auth.logout' => [AuthController::class, 'logout'],
        'Auth.me'     => [AuthController::class, 'me'],

        // ── AP Status ─────────────────────────────────────────────────────────
        // Legacy: backend/ap_status_api.php (public GET, no auth)
        'ApStatus.index' => [ApStatusController::class, 'index'],

        // ── Factory Layout ────────────────────────────────────────────────────
        // Legacy: layout_api.php (save_layout / list_layouts / get_layout / delete_layout)
        'FactoryLayout.index'   => [FactoryLayoutController::class, 'index'],   // public
        'FactoryLayout.show'    => [FactoryLayoutController::class, 'show'],    // public
        'FactoryLayout.store'   => [FactoryLayoutController::class, 'store'],   // admin
        'FactoryLayout.destroy' => [FactoryLayoutController::class, 'destroy'], // admin + ownership

        // ── Audit Trail ───────────────────────────────────────────────────
        // Legacy: model/get_audit_trail.php (?mode=list / ?mode=one)
        'AuditTrail.index' => [AuditTrailController::class, 'index'],  // any authenticated user
        'AuditTrail.show'  => [AuditTrailController::class, 'show'],   // any authenticated user

        // ── Device Data & Live Feed ───────────────────────────────────────
        // Legacy: api.php (default action / get_machine_details / search_device / get_families)
        //
        // Device.live    → raw array of all device snapshots (no wrapper)
        //                  CONTRACT.md v1.0: status strings are UPPERCASE
        //
        // Device.single  → single device extended stats by device_id
        //                  RENAMED from Device.details (Phase A) — the old name
        //                  was semantically wrong; 'details' implied a list.
        //                  EQUIVALENT legacy action: get_machine_details&device_id=X
        //
        // Device.machineDetails → full process-scoped device list with metrics
        //                         DIFFERENT from Device.single (returns array, not object)
        //                         Legacy: get_machine_details&process=mold|tuft|blister
        'Device.live'    => [DeviceController::class, 'live'],     // public
        'Device.single'  => [DeviceController::class, 'details'],  // public — RENAMED from Device.details
        'Device.history' => [DeviceController::class, 'history'],  // public
        'Family.index'   => [FamilyController::class, 'index'],    // public


        // ── Device Admin CRUD ─────────────────────────────────────────────────
        // Legacy: backend/backend.php (get_devices / add / update / delete)
        'Device.index'   => [DeviceController::class, 'index'],    // any auth
        'Device.store'   => [DeviceController::class, 'store'],    // admin
        'Device.update'  => [DeviceController::class, 'update'],   // admin
        'Device.destroy' => [DeviceController::class, 'destroy'],  // admin

        'Device.getDevices' => [DeviceController::class, 'getDevices'],

        // ── Device Actions (Work Orders) ───────────────────────────────────────
        // Legacy: backend/backend.php (action_create / update / delete / approve / reject)
        'DeviceAction.store'  => [DeviceActionController::class, 'store'],
        'DeviceAction.update' => [DeviceActionController::class, 'update'],
        'DeviceAction.storeBackend' => [DeviceActionController::class, 'storeBackend'],
        'DeviceAction.updateBackend' => [DeviceActionController::class, 'updateBackend'],
        'DeviceAction.listByDevice' => [DeviceActionController::class, 'listByDevice'],
        'DeviceAction.destroy'=> [DeviceActionController::class, 'destroy'],
        'DeviceAction.approve'=> [DeviceActionController::class, 'approve'],
        'DeviceAction.reject' => [DeviceActionController::class, 'reject'],
        'DeviceAction.createPublic' => [DeviceActionController::class, 'createPublic'],
        'DeviceAction.listV2' => [DeviceActionController::class, 'listV2'],
        'DeviceAction.listActionPlans' => [DeviceActionController::class, 'listActionPlans'],
        'DeviceAction.updateActionPlanStatus' => [DeviceActionController::class, 'updateActionPlanStatus'],
        'DeviceAction.deleteActionPlan' => [DeviceActionController::class, 'deleteActionPlan'],
        'DeviceAction.bulkUpdateActionPlanStatus' => [DeviceActionController::class, 'bulkUpdateActionPlanStatus'],
  // admin

        // ── Device Status (manual override) ────────────────────────────────────
        // Legacy: backend/backend.php (?action=update_device_status)
        'DeviceStatus.update' => [DeviceStatusController::class, 'update'], // admin

        // ── Action Plan (Kanban ordering) ────────────────────────────────────
        // Legacy: backend/backend.php (?action=update_plan_order)
        'ActionPlan.updateOrder' => [ActionPlanController::class, 'updateOrder'], // admin

        // ── Reports ────────────────────────────────────────────────────────
        // Legacy: api.php reports
        'Report.summary'    => [ReportController::class, 'summary'],
        'Report.efficiency' => [ReportController::class, 'efficiency'],
        'Report.output'     => [ReportController::class, 'output'],
        'Report.outputBulk' => [ReportController::class, 'outputBulk'],
        'Report.hourly'     => [ReportController::class, 'hourly'],
        'Report.actionsBoard' => [ReportController::class, 'actionsBoard'],
        'Report.devicesActionTable' => [ReportController::class, 'devicesActionTable'],
        'Report.previewNextCodes' => [ReportController::class, 'previewNextCodes'],
        'Report.listBackendIssues' => [ReportController::class, 'listBackendIssues'],
        'Report.countActions' => [ReportController::class, 'countActions'],
        'Report.countDeviceStatus' => [ReportController::class, 'countDeviceStatus'],
        'Report.countFlexible' => [ReportController::class, 'countFlexible'],
        'Report.getTotalCount' => [ReportController::class, 'getTotalCount'],
        'Report.getTcMeta' => [ReportController::class, 'getTcMeta'],
        'User.listUsers' => [UserController::class, 'listUsers'],

        // ── Device Machine Details (all devices by process type) ─────────────
        // Legacy: api.php ?action=get_machine_details
        'Device.machineDetails' => [DeviceController::class, 'getMachineDetails'],

        // ── [FUTURE BATCHES — ADD BELOW AS FILES ARE MIGRATED] ───────────────
        // Example:
        // 'Device.getLiveData'    => [DeviceController::class,  'live'],
        // 'Report.getSummary'     => [ReportController::class,  'summary'],
        // 'Layout.saveLayout'     => [LayoutController::class,  'store'],
    ];

    /*
    |--------------------------------------------------------------------------
    | DISPATCH
    |--------------------------------------------------------------------------
    */

    /**
     * Central dispatcher — the only method bound to a Laravel route.
     *
     * Reads `c` and `m` from the request (query string or JSON body),
     * looks up the target in DISPATCH_MAP, and calls the method via Laravel's
     * IoC container so FormRequest injection + validation runs automatically.
     */
    public function dispatch(Request $request): JsonResponse
    {
        try {
            // Extract c and m — prefer query string but fall back to body for POST
            $c = trim((string) ($request->query('c') ?? $request->input('c', '')));
            $m = trim((string) ($request->query('m') ?? $request->input('m', '')));

            if ($c === '' || $m === '') {
                return $this->notFound($c, $m);
            }

            $key = "{$c}.{$m}";

            if (! isset(self::DISPATCH_MAP[$key])) {
                return $this->notFound($c, $m);
            }

            [$controllerClass, $method] = self::DISPATCH_MAP[$key];

            /** @var Controller $controller */
            $controller = app($controllerClass);

            /** @var JsonResponse $response */
            $response = app()->call([$controller, $method]);

            return $response;
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gateway Crash: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Return a structured 404 for unmapped c/m pairs.
     * Mirrors legacy behaviour: unknown actions return a JSON error, not HTML.
     */
    private function notFound(string $c, string $m): JsonResponse
    {
        return response()->json([
            'status'  => 'error',
            'message' => "Unknown action: c={$c}&m={$m}",
        ], 404);
    }
}
