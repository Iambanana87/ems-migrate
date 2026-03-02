<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\DeviceAction\UpdateDeviceStatusRequest;
use App\Models\DeviceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * DeviceStatusController
 *
 * Admin override for connection_status and threshold_status.
 * Used for manual maintenance mode toggling.
 *
 * c=DeviceStatus & m=update → update() [admin]
 *
 * Legacy mirror: ?action=update_device_status in backend/backend.php
 */
class DeviceStatusController extends Controller
{
    use RequiresAuth;

    /**
     * POST /api/gateway?c=DeviceStatus&m=update
     *
     * Body: { device_id, connection_status?, threshold_status? }
     * At least one status field must be provided.
     */
    public function update(UpdateDeviceStatusRequest $request): JsonResponse
    {
        $this->requireRole($request, ['admin']);

        $connectionStatus = $request->getConnectionStatus();
        $thresholdStatus  = $request->getThresholdStatus();

        if ($connectionStatus === null && $thresholdStatus === null) {
            return response()->json([
                'status'  => 'error',
                'message' => 'At least one of connection_status or threshold_status is required.',
            ], 400);
        }

        $updates = array_filter([
            'connection_status' => $connectionStatus,
            'threshold_status'  => $thresholdStatus,
        ], fn ($v) => $v !== null);

        DeviceStatus::where('device_id', $request->getDeviceId())
                    ->update($updates);

        return response()->json(['status' => 'ok', 'message' => 'Status updated.']);
    }
}
