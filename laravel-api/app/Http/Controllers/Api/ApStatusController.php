<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\ApStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * ApStatusController
 *
 * Handles AP status queries dispatched by the Gateway:
 *   c=ApStatus & m=index → index()
 *
 * This endpoint is PUBLIC — no authentication required.
 * The controller is intentionally lean: it only extracts and sanitises
 * query parameters, then delegates entirely to ApStatusService.
 *
 * Legacy mirror: backend/ap_status_api.php
 *
 * Query parameters (all optional):
 *   ?names=AP01,AP02     Comma-separated AP name filter
 *   ?stale_minutes=10    Staleness threshold (metadata only — default 10)
 */
class ApStatusController extends Controller
{
    public function __construct(
        private readonly ApStatusService $apStatusService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | m=index
    |--------------------------------------------------------------------------
    */

    /**
     * Return the latest status for all (or filtered) Access Points.
     *
     * GET /api/gateway?c=ApStatus&m=index
     * GET /api/gateway?c=ApStatus&m=index&names=AP01,AP02&stale_minutes=15
     *
     * Response (exact legacy shape):
     * {
     *   "status":        "success",
     *   "stale_seconds": 600,
     *   "items": [
     *     {
     *       "name":       "AP01",
     *       "location":   "PG",
     *       "status_raw": "ONLINE",
     *       "status":     "NORMAL",
     *       "ping_ms":    2.35,
     *       "last_seen":  "2026-02-26 08:00:00"
     *     }
     *   ],
     *   "map": {
     *     "AP01": { "status": "NORMAL", "ping_ms": 2.35, "last_seen": "..." }
     *   }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        // ── Parse ?names= ─────────────────────────────────────────────────
        // Legacy: $names = $_GET['names'] ?? ''
        //         $nameFilter = array_filter(array_map('trim', explode(',', $names)))
        $names = $this->parseNames((string) ($request->query('names', '')));

        // ── Parse ?stale_minutes= ─────────────────────────────────────────
        // Legacy: $stale_minutes = max(1, (int)($_GET['stale_minutes'] ?? 10))
        $staleMinutes = max(1, (int) $request->query('stale_minutes', 10));

        // ── Delegate to service ───────────────────────────────────────────
        $result = $this->apStatusService->getStatuses($names, $staleMinutes);

        return response()->json($result);
    }

    /*
    |--------------------------------------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------------------------------------
    */

    /**
     * Parse a comma-separated names string into a clean list of non-empty strings.
     *
     * Legacy: array_filter(array_map('trim', explode(',', $names)))
     *
     * Example: "AP01, AP02 , ,AP03" → ['AP01', 'AP02', 'AP03']
     *
     * @return list<string>
     */
    private function parseNames(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        return array_values(
            array_filter(
                array_map('trim', explode(',', $raw)),
                fn (string $n) => $n !== '',
            )
        );
    }
}
