<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * FamilyController
 *
 * Returns the distinct product family / process list.
 * Used by the FamilyView.vue filter sidebar.
 *
 * c=Family & m=index → index() [public]
 *
 * Legacy mirror: ?action=get_families in api.php
 */
class FamilyController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | m=index — Distinct process / family list
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=Family&m=index[&process=mold]
     *
     * ── LEGACY SQL (api.php:884) ──────────────────────────────────────────
     * SELECT DISTINCT product FROM devices
     * WHERE display_type = ?           -- bound to $_GET['process'] ?? 'mold'
     *   AND product IS NOT NULL
     *   AND product != ''
     * ORDER BY product ASC
     *
     * ── DRIFT ROOT CAUSE (Phase B investigation, 2026-03-05) ─────────────
     * Three differences from the previous Laravel implementation:
     *
     *   1. COLUMN: Legacy selects `product`. Laravel was selecting `process`.
     *              These are different columns — product is the family label,
     *              process is the process type identifier (mold/tuft/blister).
     *
     *   2. FILTER: Legacy filters by `display_type = $_GET['process'] ?? 'mold'`.
     *              Laravel had no display_type filter — returning all rows.
     *
     *   3. GUARD:  Legacy excludes empty strings (product != '').
     *              Laravel only excluded NULL.
     *
     * These three gaps cause: wrong column values + wrong row count + extra
     * empty-string entries in the result set.
     *
     * ── FIX ───────────────────────────────────────────────────────────────
     * BEFORE:
     *   Device::query()
     *       ->whereNotNull('process')
     *       ->orderBy('process')
     *       ->distinct()
     *       ->pluck('process')
     *
     * AFTER (this implementation):
     *   Matches legacy SQL exactly — no business logic change.
     *
     * @parity-verified Family.index — Phase B value fix (2026-03-05)
     */
    public function index(Request $request): JsonResponse
    {
        // Legacy: $_GET['process'] ?? 'mold'
        // Binds to the display_type column — selects devices of that process type.
        $displayType = (string) $request->input('process', 'mold');

        $families = Device::query()
            // Legacy: WHERE display_type = ?
            ->where('display_type', $displayType)
            // Legacy: AND product IS NOT NULL
            ->whereNotNull('product')
            // Legacy: AND product != ''
            ->where('product', '!=', '')
            // Legacy: ORDER BY product ASC
            ->orderBy('product')
            // Legacy: SELECT DISTINCT product
            ->distinct()
            ->pluck('product')
            ->values()
            ->all();

        // Raw array — matches legacy json_encode($families).
        return response()->json($families);
    }


}
