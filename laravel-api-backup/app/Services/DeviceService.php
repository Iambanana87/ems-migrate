<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Blister;
use App\Models\Device;
use App\Models\DeviceStatus;
use App\Models\Mold;
use App\Models\Tuft;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * DeviceService
 *
 * Core calculation engine for the EMS monitoring system.
 * Mirrors the business logic from legacy api.php for:
 *   - Live dashboard feed           (?action= default)
 *   - Per-device detailed stats     (?action=get_machine_details)
 *   - Historical raw record search  (?action=search_device)
 *
 * CAPACITY FORMULAS (exact parity with legacy):
 *   Mold:    (3600 / target_limit) × cavities
 *   Tuft:    target_limit × 60              (target_limit = pcs/min)
 *   Blister: target_limit × brushes_per_cycle × 60
 *
 * EFFICIENCY FORMULAS (exact parity with legacy):
 *   Mold:    (target_limit / actual_cycle_time) × 100  [capped at 120]
 *   Tuft:    (actual_output / target_output) × 100      [capped at 120]
 *   Blister: (actual_output / target_output) × 100      [capped at 120]
 *
 * All efficiency values are capped at 120 (%) to prevent runaway display.
 */
final class DeviceService
{
    private const EFFICIENCY_CAP   = 120.0;  // max displayable efficiency %
    private const DEFAULT_HISTORY  = 10;      // fallback when history_count is null
    private const DEFAULT_SORT_COL = 'datetime';

    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | DEVICE CRUD (admin actions from backend/backend.php)
    |--------------------------------------------------------------------------
    */

    /**
     * Paginated device list with optional filters.
     * Legacy mirror: ?action=get_devices
     *
     * @return array{status:string,total:int,page:int,page_size:int,total_pages:int,data:list<array<string,mixed>>}
     */
    public function listDevices(int $page, int $pageSize, string $search = '', string $type = '', string $process = ''): array
    {
        $query = Device::query();

        if ($type !== '') {
            $query->where('display_type', $type);
        }
        if ($process !== '') {
            $query->where('process', $process);
        }
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('device_id', 'LIKE', "%{$search}%")
                  ->orWhere('product',   'LIKE', "%{$search}%");
            });
        }

        $total      = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;
        $data       = $query->orderBy('device_id')
                            ->offset(($page - 1) * $pageSize)
                            ->limit($pageSize)
                            ->get()
                            ->toArray();

        return [
            'status'      => 'ok',
            'total'       => $total,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_pages' => $totalPages,
            'data'        => $data,
        ];
    }

    /**
     * Create a new device + auto-create its device_status row.
     * Legacy mirror: ?action=add
     *
     * @param  array<string, mixed>  $data
     * @throws \RuntimeException  if device_id already exists
     */
    public function addDevice(array $data, string $who): Device
    {
        return DB::transaction(function () use ($data, $who): Device {
            $device = Device::create($data);

            DeviceStatus::create([
                'device_id'          => $device->device_id,
                'connection_status'  => 'Disconnected',
                'threshold_status'   => 'Normal',
            ]);

            // Audit — before = empty array (new record)
            $this->audit->log('device_add', null, [], $device->toArray(), $who);

            return $device->fresh();
        });
    }

    /**
     * Update device config fields.
     * Legacy mirror: ?action=update
     *
     * @param  array<string, mixed>  $data
     * @throws ModelNotFoundException
     */
    public function updateDevice(string $deviceId, array $data, string $who, ?string $reason = null): Device
    {
        $device = Device::where('device_id', $deviceId)->firstOrFail();

        return DB::transaction(function () use ($device, $data, $who, $reason): Device {
            $before = $device->toArray();
            $device->update($data);
            $after  = $device->fresh()->toArray();

            $this->audit->log('device_update', $reason, $before, $after, $who);

            return $device->fresh();
        });
    }

    /**
     * Delete a device. FK CASCADE removes device_status automatically.
     * Legacy mirror: ?action=delete
     *
     * @throws ModelNotFoundException
     */
    public function deleteDevice(string $deviceId, string $who): void
    {
        $device = Device::where('device_id', $deviceId)->firstOrFail();

        DB::transaction(function () use ($device, $who): void {
            $snapshot = $device->toArray();
            $device->delete();
            // Audit after delete — after = empty array (record gone)
            $this->audit->log('device_delete', null, $snapshot, [], $who);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | LIVE FEED — all devices
    |--------------------------------------------------------------------------
    */

    /**
     * Build the full live device feed for the dashboard.
     *
     * Strategy:
     *   1. Load all devices with status + cached live_data eager-loaded.
     *   2. For each device, prefer the pre-computed live_data cache if fresh.
     *   3. Fall back to a live DB query against mold/tuft/blister if cache is stale or absent.
     *
     * Legacy mirror: default ?action= handler in api.php
     *
     * @return list<array<string, mixed>>
     */
    public function getLiveFeed(): array
    {
        $devices = Device::withLiveFeed()->get();

        return $devices->map(function (Device $device): array {
            // Use pre-computed live_data cache if available
            if ($device->liveData && is_array($device->liveData->live_data)) {
                $cached = $device->liveData->live_data;

                return array_merge($cached, [
                    'device_id'          => $device->device_id,
                    'display_type'       => $device->display_type,
                    'process'            => $device->process,
                    'flex'               => $device->flex,
                    'status'             => $device->status?->connection_status ?? 'Disconnected',
                    'threshold_status'   => $device->status?->threshold_status  ?? 'Normal',
                    'last_heartbeat'     => $device->status?->last_heartbeat?->format('Y-m-d H:i:s'),
                    'target_limit'       => $device->target_limit,
                    'capacity'           => $this->computeCapacity($device),
                ]);
            }

            // Cache absent — compute live from IoT tables
            return $this->buildDeviceSnapshot($device);
        })->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | MACHINE DETAILS — single device with last-N trend
    |--------------------------------------------------------------------------
    */

    /**
     * Return extended stats for a single device, including the rolling
     * last-N cycles array used by the ChartModal in the frontend.
     *
     * Legacy mirror: ?action=get_machine_details
     *
     * @return array<string, mixed>
     * @throws ModelNotFoundException  if device_id not found (→ 404)
     */
    public function getMachineDetails(string $deviceId): array
    {
        $device = Device::where('device_id', $deviceId)
            ->with('status')
            ->firstOrFail();

        $limit  = $device->history_count ?? self::DEFAULT_HISTORY;
        $cycles = $this->getLastNCycles($device, $limit);

        $snapshot = $this->buildDeviceSnapshot($device);

        // Append the rolling cycle array for the chart
        $snapshot['last_n_cycles']  = $cycles;
        $snapshot['history_count']  = $limit;

        return $snapshot;
    }

    /*
    |--------------------------------------------------------------------------
    | HISTORY SEARCH — raw records for a device
    |--------------------------------------------------------------------------
    */

    /**
     * Return raw IoT records for a device within a date range.
     *
     * Legacy mirror: ?action=search_device
     *
     * @return array{status:string, total:int, data:list<array<string,mixed>>}
     * @throws ModelNotFoundException  if device_id not found (→ 404)
     */
    public function searchHistory(
        string  $deviceId,
        ?string $from,
        ?string $to,
        int     $limit = 500,
    ): array {
        // Validate device exists first (gives clean 404 in controller)
        $device = Device::where('device_id', $deviceId)->firstOrFail();

        $rows = match ($device->display_type) {
            'mold'    => Mold::where('device_id', $deviceId)
                             ->inDateRange($from, $to)
                             ->orderByDesc('datetime')
                             ->limit($limit)
                             ->get()
                             ->map(fn (Mold $r) => [
                                 'id'         => $r->id,
                                 'datetime'   => $r->datetime?->format('Y-m-d H:i:s'),
                                 'product'    => $r->product,
                                 'cavities'   => $r->cavities,
                                 'cycle_time' => $r->cycle_time,
                             ]),

            'tuft'    => Tuft::where('device_id', $deviceId)
                             ->inDateRange($from, $to)
                             ->orderByDesc('datetime')
                             ->limit($limit)
                             ->get()
                             ->map(fn (Tuft $r) => [
                                 'id'       => $r->id,
                                 'datetime' => $r->datetime?->format('Y-m-d H:i:s'),
                                 'product'  => $r->product,
                                 'process'  => $r->process,
                                 'output'   => $r->output,
                             ]),

            'blister' => Blister::where('device_id', $deviceId)
                                ->inDateRange($from, $to)
                                ->orderByDesc('datetime')
                                ->limit($limit)
                                ->get()
                                ->map(fn (Blister $r) => [
                                    'id'              => $r->id,
                                    'datetime'        => $r->datetime?->format('Y-m-d H:i:s'),
                                    'product'         => $r->product,
                                    'BrushesperCycle' => $r->BrushesperCycle,
                                    'cyclecount'      => $r->cyclecount,
                                    'output'          => $r->output,
                                ]),

            default => collect(),
        };

        return [
            'status' => 'ok',
            'total'  => $rows->count(),
            'data'   => $rows->values()->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | CAPACITY COMPUTATION
    |--------------------------------------------------------------------------
    */

    /**
     * Compute theoretical capacity (units/hour) for a device.
     *
     * Exact formulas from legacy api.php:
     *   Mold:    (3600 / target_limit) × cavities
     *   Tuft:    target_limit × 60
     *   Blister: target_limit × brushes_per_cycle × 60
     *
     * Returns 0 if required config params are missing or zero.
     */
    public function computeCapacity(Device $device): float
    {
        $target = (float) ($device->target_limit ?? 0);

        if ($target <= 0) {
            return 0.0;
        }

        return match ($device->display_type) {
            'mold'    => (3600 / $target) * (int) ($device->cavities ?? 1),
            'tuft'    => $target * 60,
            'blister' => $target * (int) ($device->brushes_per_cycle ?? 1) * 60,
            default   => 0.0,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | EFFICIENCY COMPUTATION
    |--------------------------------------------------------------------------
    */

    /**
     * Compute efficiency (%) from a raw IoT record and device config.
     *
     * Exact formulas from legacy api.php, capped at EFFICIENCY_CAP (120%):
     *   Mold:    (target_limit / actual_cycle_time) × 100
     *   Tuft:    (actual_output / target_output_per_interval) × 100
     *   Blister: (actual_output / target_output_per_interval) × 100
     *
     * Returns 0 when required values are missing or zero to avoid divide-by-zero.
     *
     * @param  array<string, mixed>  $record  Normalised record from getLatestRecord()
     */
    public function computeEfficiency(Device $device, array $record): float
    {
        $target = (float) ($device->target_limit ?? 0);

        if ($target <= 0) {
            return 0.0;
        }

        $efficiency = match ($device->display_type) {
            'mold' => (function () use ($target, $record): float {
                $cycleTime = (float) ($record['cycle_time'] ?? 0);
                return $cycleTime > 0 ? ($target / $cycleTime) * 100 : 0.0;
            })(),

            'tuft' => (function () use ($target, $record): float {
                // target_limit = target pcs/min; convert to per-poll-period
                // output in record is raw pcs counted this interval
                $actual = (float) ($record['output'] ?? 0);
                $targetPcs = $target * 60; // pcs per hour → compare against rolling sum
                return $targetPcs > 0 ? ($actual / $targetPcs) * 100 : 0.0;
            })(),

            'blister' => (function () use ($target, $record, $device): float {
                $actual    = (float) ($record['output'] ?? 0);
                $brushes   = (int)   ($device->brushes_per_cycle ?? 1) ?: 1;
                $targetPcs = $target * $brushes * 60;
                return $targetPcs > 0 ? ($actual / $targetPcs) * 100 : 0.0;
            })(),

            default => 0.0,
        };

        return min(round($efficiency, 2), self::EFFICIENCY_CAP);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Build the standard device snapshot array from a live DB query.
     * Used by getLiveFeed() fallback and getMachineDetails().
     *
     * @return array<string, mixed>
     */
    private function buildDeviceSnapshot(Device $device): array
    {
        $limit  = $device->history_count ?? self::DEFAULT_HISTORY;
        $latest = $this->getLatestRecord($device, $limit);

        $efficiency = $latest ? $this->computeEfficiency($device, $latest) : 0.0;
        $capacity   = $this->computeCapacity($device);

        return [
            'device_id'        => $device->device_id,
            'display_type'     => $device->display_type,
            'process'          => $device->process,
            'product'          => $latest['product'] ?? $device->product,
            'flex'             => $device->flex,
            'status'           => $device->status?->connection_status ?? 'Disconnected',
            'threshold_status' => $device->status?->threshold_status  ?? 'Normal',
            'last_heartbeat'   => $device->status?->last_heartbeat?->format('Y-m-d H:i:s'),
            'efficiency'       => $efficiency,
            'output'           => (int) ($latest['output'] ?? 0),
            'cycle_time'       => isset($latest['cycle_time']) ? round((float) $latest['cycle_time'], 2) : null,
            'target_limit'     => $device->target_limit,
            'capacity'         => round($capacity, 2),
        ];
    }

    /**
     * Fetch the latest record from the appropriate IoT table and normalise
     * it to a consistent array shape for computeEfficiency().
     *
     * @return array<string, mixed>|null
     */
    private function getLatestRecord(Device $device, int $limit = 1): ?array
    {
        return match ($device->display_type) {
            'mold'    => Mold::latestForDevice($device->device_id, $limit)
                             ->get()
                             ->map(fn (Mold $r) => [
                                 'product'    => $r->product,
                                 'cycle_time' => $r->cycle_time,
                                 'output'     => $r->cavities, // shots × cavities for output count
                             ])
                             ->first(),

            'tuft'    => Tuft::latestForDevice($device->device_id, $limit)
                             ->get()
                             ->map(fn (Tuft $r) => [
                                 'product' => $r->product,
                                 'output'  => $r->output,
                             ])
                             ->first(),

            'blister' => Blister::latestForDevice($device->device_id, $limit)
                                ->get()
                                ->map(fn (Blister $r) => [
                                    'product' => $r->product,
                                    'output'  => $r->getOutputCount(),
                                ])
                                ->first(),

            default => null,
        };
    }

    /**
     * Get the last N cycle_time / output values as a plain array.
     * Used by getMachineDetails() to populate the trend chart.
     *
     * @return list<float|int>
     */
    private function getLastNCycles(Device $device, int $n): array
    {
        return match ($device->display_type) {
            'mold'    => Mold::latestForDevice($device->device_id, $n)
                             ->pluck('cycle_time')
                             ->map(fn ($v) => round((float) $v, 2))
                             ->values()
                             ->all(),

            'tuft'    => Tuft::latestForDevice($device->device_id, $n)
                             ->pluck('output')
                             ->map(fn ($v) => (int) $v)
                             ->values()
                             ->all(),

            'blister' => Blister::latestForDevice($device->device_id, $n)
                                ->get()
                                ->map(fn (Blister $r) => $r->getOutputCount())
                                ->values()
                                ->all(),

            default => [],
        };
    }
}
