<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\Device\AddDeviceRequest;
use App\Http\Requests\Device\DeviceDetailsRequest;
use App\Http\Requests\Device\DeviceHistoryRequest;
use App\Http\Requests\Device\DeviceIndexRequest;
use App\Http\Requests\Device\UpdateDeviceRequest;
use App\Services\DeviceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * DeviceController
 *
 * Handles device data queries dispatched by the Gateway:
 *   c=Device & m=live    → live()    [public] Full dashboard feed
 *   c=Device & m=details → details() [public] Per-device extended stats
 *   c=Device & m=history → history() [public] Historical raw records
 *
 * All endpoints are public — no authentication required.
 * This controller is intentionally thin; all business logic is in DeviceService.
 *
 * Legacy mirror: default action + get_machine_details + search_device in api.php
 */
class DeviceController extends Controller
{
    use RequiresAuth;

    public function __construct(
        private readonly DeviceService $deviceService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | m=live — Full dashboard live feed
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=Device&m=live
     *
     * Returns the live status, efficiency, and output for ALL devices.
     * Prefers pre-computed cache in live_device_data; falls back to live query.
     *
     * Legacy mirror: default ?action= handler (no action param)
     *
     * Response: { "status": "ok", "data": [ { device snapshot }, ... ] }
     */
    public function live(Request $request): JsonResponse
    {
        $data = $this->deviceService->getLiveFeed();

        return response()->json([
            'status' => 'ok',
            'data'   => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=details — Single device extended stats
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=Device&m=details&device_id=MOLD_01
     *
     * Returns extended stats including last-N cycle trend array.
     *
     * Legacy mirror: ?action=get_machine_details&device_id=...
     *
     * Response: { "status": "ok", "data": { ...snapshot, last_n_cycles: [...] } }
     */
    public function details(DeviceDetailsRequest $request): JsonResponse
    {
        try {
            $data = $this->deviceService->getMachineDetails($request->getDeviceId());
        } catch (ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Device not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => $data,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=history — Historical raw records for a device
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=Device&m=history&device_id=MOLD_01&from=...&to=...
     *
     * Returns raw IoT records from the appropriate table (mold/tuft/blister).
     *
     * Legacy mirror: ?action=search_device
     *
     * Response: { "status": "ok", "total": N, "data": [...raw records...] }
     */
    public function history(DeviceHistoryRequest $request): JsonResponse
    {
        try {
            $result = $this->deviceService->searchHistory(
                deviceId: $request->getDeviceId(),
                from:     $request->getFrom(),
                to:       $request->getTo(),
                limit:    $request->getLimit(),
            );
        } catch (ModelNotFoundException) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Device not found.',
            ], 404);
        }

        return response()->json($result);
    }

    /*
    |--------------------------------------------------------------------------
    | ADMIN CRUD (from backend/backend.php)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=Device&m=index — any authenticated user.
     * Legacy mirror: ?action=get_devices
     */
    public function index(DeviceIndexRequest $request): JsonResponse
    {
        $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));

        $result = $this->deviceService->listDevices(
            page:     $request->getPage(),
            pageSize: $request->getPageSize(),
            search:   $request->getSearch(),
            type:     $request->getType(),
            process:  $request->getProcess(),
        );

        return response()->json($result);
    }

    /**
     * POST /api/gateway?c=Device&m=store — admin only.
     * Legacy mirror: ?action=add
     */
    public function store(AddDeviceRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            $device = $this->deviceService->addDevice($request->validated(), $who);
        } catch (\Illuminate\Database\QueryException) {
            return response()->json(['status' => 'error', 'message' => 'Device ID already exists.'], 409);
        }

        return response()->json(['status' => 'ok', 'message' => 'Device created.', 'data' => $device->toArray()]);
    }

    /**
     * POST /api/gateway?c=Device&m=update — admin only.
     * Legacy mirror: ?action=update
     */
    public function update(UpdateDeviceRequest $request): JsonResponse
    {
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            $device = $this->deviceService->updateDevice(
                deviceId: (string) $request->validated('device_id'),
                data:     $request->updateData(),
                who:      $who,
                reason:   $request->validated('reason'),
            );
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Device not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'message' => 'Device updated.', 'data' => $device->toArray()]);
    }

    /**
     * POST /api/gateway?c=Device&m=destroy — admin only.
     * Legacy mirror: ?action=delete
     */
    public function destroy(Request $request): JsonResponse
    {
        $claims   = $this->requireRole($request, ['admin']);
        $who      = $this->whoFromClaims($claims);
        $deviceId = trim((string) $request->input('device_id', ''));

        if ($deviceId === '') {
            return response()->json(['status' => 'error', 'message' => 'device_id is required.'], 400);
        }

        try {
            $this->deviceService->deleteDevice($deviceId, $who);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Device not found.'], 404);
        }

        return response()->json(['status' => 'ok', 'message' => 'Device deleted.']);
    }
}
