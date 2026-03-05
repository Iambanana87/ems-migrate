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

    /*
    |--------------------------------------------------------------------------
    | m=machineDetails — All devices by process type with live metrics
    |--------------------------------------------------------------------------
    | STRICT PARITY with api.php:1291-1499 (get_machine_details).
    | - PUBLIC endpoint. No auth. No transaction. No audit.
    | - Raw DB query. JSON_NUMERIC_CHECK. No wrapper.
    | - Includes connection/breach/status logic, filtering, debug mode.
    */

    public function getMachineDetails(Request $request): JsonResponse
    {
        $process_type  = $request->query('process', 'mold');
        $status_filter = strtolower($request->query('status', 'total'));

        $sql = "
            SELECT 
                d.device_id, d.client, d.product AS family, d.process, d.cavities AS mold_cavity,
                d.capacity AS capacity_per_hr, d.target_limit AS target,
                d.upper_limit, d.lower_limit, d.efficiency_lower_limit, d.frequency, d.brushes_per_cycle, d.hole_per_brush,
                d.total_count, d.cavity_count, d.total_rpm, d.total_cycle, 
                (COALESCE(d.flex,'0') = '1') AS is_flex,
                EXISTS (
                    SELECT 1
                    FROM device_actions da
                    WHERE BINARY da.device_id = BINARY d.device_id
                        AND da.status <> 'cancelled'
                        AND NOT (da.status='done' AND da.approval_status='approved')
                    LIMIT 1
                ) AS has_action,
                ldd.live_data, ldd.last_updated
            FROM devices d
            LEFT JOIN live_device_data ldd ON d.device_id = ldd.device_id
            WHERE d.display_type = ?
            ORDER BY d.device_id;
        ";

        $devices = \Illuminate\Support\Facades\DB::select($sql, [$process_type]);
        $results = [];
        $now = time();

        $reportService = app(\App\Services\ReportService::class);

        foreach ($devices as $deviceObj) {
            $device = (array) $deviceObj;

            // 1. Parse live_data
            $rawLiveData = $device['live_data'] ?? '';
            if (!is_string($rawLiveData) || trim($rawLiveData) === '') {
                $live = [];
            } else {
                $live = json_decode($rawLiveData, true) ?: [];
            }

            // live_output fallback: output -> rate -> speed -> 0
            $live_output = isset($live['output']) ? (float)$live['output'] : null;
            if ($live_output === null) {
                $live_output = isset($live['rate']) ? (float)$live['rate'] : (isset($live['speed']) ? (float)$live['speed'] : 0);
            }
            $live_cyclecount = (float)($live['cyclecount'] ?? $live['cycle_count'] ?? 0);

            // 2. Metric calculation
            // STRICT PARITY: Legacy only calls calculateMoldMetrics if $live_data is truthy.
            // When live_data is null/empty, $calc remains null and metric keys are omitted.
            // 2. Metrics Calculation (Match legacy flow: always call even if disconnected)
            switch ($process_type) {
                case 'mold':    $calc = $reportService->calculateMoldMetrics($live, $device);    break;
                case 'tuft':    $calc = $reportService->calculateTuftMetrics($live, $device);    break;
                case 'blister': $calc = $reportService->calculateBlisterMetrics($live, $device); break;
                default:        $calc = [];
            }

            // A. Connection status
            $dataTimestamp = 0;
            if (isset($live['datetime']) && $live['datetime']) {
                $dataTimestamp = strtotime($live['datetime']);
            } elseif (!empty($device['last_updated'])) {
                $dataTimestamp = strtotime($device['last_updated']);
            }
            $freq = (int)($device['frequency'] ?? 300);
            if ($freq <= 0) $freq = 300;
            $isConnected = ($dataTimestamp > 0 && ($now - $dataTimestamp) <= $freq);

            // B. Breach detection (only if connected)
            $isBreached = false;
            if ($isConnected) {
                $eff = (float)($calc['efficiency'] ?? 0);
                $effLL = isset($device['efficiency_lower_limit']) ? (float)$device['efficiency_lower_limit'] : 0;
                $limitLower = isset($device['lower_limit']) ? (float)$device['lower_limit'] : 0;
                $limitUpper = isset($device['upper_limit']) ? (float)$device['upper_limit'] : 0;

                if ($process_type === 'mold') {
                    if ($effLL > 0 && $eff < $effLL) $isBreached = true;
                    $cyc = (float)($live['cycle_time'] ?? 0);
                    if ($cyc > 0) {
                        if ($limitLower > 0 && $cyc < $limitLower) $isBreached = true;
                        if ($limitUpper > 0 && $cyc > $limitUpper) $isBreached = true;
                    }
                } elseif ($process_type === 'tuft') {
                    if ($effLL > 0 && $eff < $effLL) $isBreached = true;
                    if ($limitLower > 0 && $live_output < $limitLower) $isBreached = true;
                    if ($limitUpper > 0 && $live_output > $limitUpper) $isBreached = true;
                } elseif ($process_type === 'blister') {
                    if ($effLL > 0 && $eff < $effLL) $isBreached = true;
                    if ($limitLower > 0 && $live_cyclecount < $limitLower) $isBreached = true;
                    if ($limitUpper > 0 && $live_cyclecount > $limitUpper) $isBreached = true;
                }
            }

            // C. Final status
            if (!$isConnected) {
                $status = 'DISCONNECTED';
            } elseif ($isBreached) {
                $status = 'BREACHED';
            } else {
                $status = 'NORMAL';
            }

            $isFlex    = ((int)($device['is_flex']    ?? 0) === 1);
            $hasAction = ((int)($device['has_action'] ?? 0) === 1);

            $lost_time = (float)($calc['idle_breakdown'] ?? $calc['lost_time'] ?? $live['lost_time'] ?? 0);

            // Base DTO — STRICT PARITY with api.php:1425-1455
            $row = [
                'mold_id'         => $device['device_id'],
                'client'          => $device['client'] ?? null,
                'family'          => $device['family'] ?? null,
                'process'         => $device['process'] ?? null,
                'mold_cavity'     => (int)($device['mold_cavity'] ?? 0),
                'actual_cavity'   => (int)($live['cavities'] ?? 0),
                'capacity_per_hr' => (float)($device['capacity_per_hr'] ?? $device['capacity'] ?? 0),
                'efficiency'      => (float)($calc['efficiency'] ?? 0),
                'efficiency_lower_limit' => isset($device['efficiency_lower_limit']) ? (float)$device['efficiency_lower_limit'] : null,
                'current_cycle'   => $process_type === 'mold'
                               ? (float)($live['cycle_time'] ?? 0)
                               : ($process_type === 'tuft' ? (float)$live_output : (float)$live_cyclecount),
                'output'          => (float)$live_output,
                'cyclecount'      => (float)$live_cyclecount,
                'total_count'     => (int)($device['total_count'] ?? 0),
                'cavity_count'    => $process_type === 'mold' ? (int)($device['cavity_count'] ?? 0) : null,
                'total_rpm'       => $process_type === 'tuft' ? (float)($device['total_rpm'] ?? 0) : null,
                'total_cycle'     => $process_type === 'blister' ? (float)($device['total_cycle'] ?? 0) : null,
                'target'          => (float)($device['target'] ?? 0) ?: null,
                'bush_per_cycle'  => ($process_type === 'blister') ? (isset($live['BrushesperCycle']) ? (float)$live['BrushesperCycle'] : null) : null,
                'hole_per_brush'  => isset($device['hole_per_brush']) ? (int)$device['hole_per_brush'] : null,
                'upper_limit'     => (float)($device['upper_limit'] ?? 0) ?: null,
                'lower_limit'     => (float)($device['lower_limit'] ?? 0) ?: null,
                'total_lost_pcs'  => (float)($calc['loss_pcs'] ?? 0),
                'lost_time'       => $lost_time,
                'status'          => $status,
                'last_updated'    => $device['last_updated'] ?? null,
                'is_flex'         => $isFlex,
                'has_action'      => $hasAction,
            ];

            $results[] = $row;
        }

        // Filter
        if ($status_filter !== 'total') {
            $sf = strtolower($status_filter);
            if ($sf === 'running') {
                $results = array_values(array_filter($results, function ($r) {
                    $st = strtoupper($r['status']);
                    return $st === 'NORMAL' || $st === 'BREACHED';
                }));
            } elseif ($sf === 'breakdown') {
                $results = array_values(array_filter($results, function ($r) {
                    return strtoupper($r['status']) === 'DISCONNECTED';
                }));
            } elseif ($sf === 'warning') {
                $results = array_values(array_filter($results, function ($r) {
                    return strtoupper($r['status']) === 'BREACHED';
                }));
            }
        }

        // Debug mode
        if ($request->query('debug') === '1') {
            $counts = [];
            foreach ($results as $r) {
                $k = strtoupper($r['status']);
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
            return response()->json([
                'data'    => $results,
                'counts'  => $counts,
                'filter'  => $status_filter,
                'process' => $process_type,
            ], 200, ['Content-Type' => 'application/json'], \JSON_NUMERIC_CHECK);
        }

        return response()->json($results, 200, ['Content-Type' => 'application/json'], \JSON_NUMERIC_CHECK);
    }
}
