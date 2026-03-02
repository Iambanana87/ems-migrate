<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApLog;

/**
 * ApStatusService
 *
 * Encapsulates all business logic from legacy backend/ap_status_api.php:
 *   - Latest-per-AP database query (via ApLog Eloquent scopes)
 *   - Status normalization (ONLINE → NORMAL, OFFLINE → DISCONNECTED, etc.)
 *   - UNKNOWN entries for requested-but-absent APs
 *   - Response shape assembly: items[] + map{}
 */
final class ApStatusService
{
    /*
    |--------------------------------------------------------------------------
    | STATUS NORMALISATION MAP
    |--------------------------------------------------------------------------
    | Raw values observed in production (from ESP32 firmware):
    |
    |   NORMAL group:       ONLINE, OK, CONNECTED
    |   DISCONNECTED group: OFFLINE, DISCONNECT, DISCONNECTED
    |   BREACHED group:     BREACHED
    |   Anything else:      UNKNOWN
    |--------------------------------------------------------------------------
    */
    private const STATUS_MAP = [
        'ONLINE'       => 'NORMAL',
        'OK'           => 'NORMAL',
        'CONNECTED'    => 'NORMAL',
        'OFFLINE'      => 'DISCONNECTED',
        'DISCONNECT'   => 'DISCONNECTED',
        'DISCONNECTED' => 'DISCONNECTED',
        'BREACHED'     => 'BREACHED',
    ];

    /*
    |--------------------------------------------------------------------------
    | PUBLIC API
    |--------------------------------------------------------------------------
    */

    /**
     * Retrieve the latest status for all (or a filtered set of) Access Points.
     *
     * Mirrors: the full logic in backend/ap_status_api.php
     *
     * @param  list<string>  $names         AP names to filter (empty = all APs)
     * @param  int           $staleMinutes  Staleness threshold; included as metadata
     *                                      in the response. Not used to filter rows
     *                                      (front-end applies staleness visually).
     *
     * @return array{
     *   status: string,
     *   stale_seconds: int,
     *   items: list<array<string,mixed>>,
     *   map: array<string, array<string,mixed>>
     * }
     */
    public function getStatuses(array $names = [], int $staleMinutes = 10): array
    {
        // 1) Query: latest row per AP, optional name filter
        $rows = ApLog::latestPerAp()
            ->withNames($names)
            ->get();

        // 2) Build items array from DB rows
        $items      = [];
        $foundNames = [];

        foreach ($rows as $row) {
            /** @var ApLog $row */
            $rawStatus   = strtoupper((string) $row->status);
            $normStatus  = $this->normalizeStatus($rawStatus);
            $foundNames[] = $row->ap_name;

            $items[] = [
                'name'       => $row->ap_name,
                'location'   => $row->location,
                'status_raw' => $rawStatus,
                'status'     => $normStatus,
                'ping_ms'    => $row->ping_ms !== null ? round((float) $row->ping_ms, 2) : null,
                'last_seen'  => $row->logged_at?->format('Y-m-d H:i:s'),
            ];
        }

        // 3) For any requested AP name not found in DB, append an UNKNOWN entry.
        //    Legacy: "Unknown APs get status: UNKNOWN" in the response items list.
        if (! empty($names)) {
            $missing = array_diff($names, $foundNames);
            foreach ($missing as $missingName) {
                $items[] = [
                    'name'       => $missingName,
                    'location'   => null,
                    'status_raw' => 'UNKNOWN',
                    'status'     => 'UNKNOWN',
                    'ping_ms'    => null,
                    'last_seen'  => null,
                ];
            }
        }

        // 4) Build the map{} for O(1) keyed lookups in the frontend JS
        $map = $this->buildMap($items);

        return [
            'status'        => 'success',
            'stale_seconds' => $staleMinutes * 60,
            'items'         => $items,
            'map'           => $map,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize a raw status string from the database to the canonical
     * set that the frontend understands.
     *
     * Pure function — no side effects, fully testable in isolation.
     *
     * @param  string  $raw  Already uppercased by the caller.
     * @return string        'NORMAL' | 'DISCONNECTED' | 'BREACHED' | 'UNKNOWN'
     */
    public function normalizeStatus(string $raw): string
    {
        return self::STATUS_MAP[$raw] ?? 'UNKNOWN';
    }

    /**
     * Convert items[] into a map{} keyed by AP name.
     *
     * Legacy: `$response['map'] = array_column($items, null, 'name')`
     * Produces: { "AP01": { status, ping_ms, last_seen }, ... }
     *
     * The map omits `name`, `status_raw` to keep it concise — matches legacy
     * exactly (frontend only uses status/ping_ms/last_seen from the map).
     *
     * @param  list<array<string,mixed>>     $items
     * @return array<string, array<string,mixed>>
     */
    private function buildMap(array $items): array
    {
        $map = [];

        foreach ($items as $item) {
            $map[(string) $item['name']] = [
                'status'    => $item['status'],
                'ping_ms'   => $item['ping_ms'],
                'last_seen' => $item['last_seen'],
            ];
        }

        return $map;
    }
}
