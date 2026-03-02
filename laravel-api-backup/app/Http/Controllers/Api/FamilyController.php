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
     * GET /api/gateway?c=Family&m=index
     *
     * Legacy: SELECT DISTINCT process FROM devices WHERE process IS NOT NULL
     *         ORDER BY process ASC
     *
     * Response: { "status": "ok", "data": ["Injection", "Tufting", "Blister"] }
     */
    public function index(Request $request): JsonResponse
    {
        $families = Device::query()
            ->whereNotNull('process')
            ->orderBy('process')
            ->distinct()
            ->pluck('process')
            ->values()
            ->all();

        return response()->json([
            'status' => 'ok',
            'data'   => $families,
        ]);
    }
}
