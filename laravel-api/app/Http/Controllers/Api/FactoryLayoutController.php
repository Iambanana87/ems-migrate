<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\Layout\SaveLayoutRequest;
use App\Services\FactoryLayoutService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * FactoryLayoutController
 *
 * Handles factory floor layout CRUD dispatched by the Gateway:
 *   c=FactoryLayout & m=index   → index()   [public]
 *   c=FactoryLayout & m=show    → show()    [public]
 *   c=FactoryLayout & m=store   → store()   [admin]
 *   c=FactoryLayout & m=destroy → destroy() [admin + ownership]
 *
 * Legacy mirror: layout_api.php
 */
class FactoryLayoutController extends Controller
{
    use RequiresAuth;

    public function __construct(
        private readonly FactoryLayoutService $layoutService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | m=index — Paginated list (PUBLIC)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=FactoryLayout&m=index
     *
     * Optional query params:
     *   ?page=1          Default: 1
     *   ?page_size=10    Default: 10  (capped at 100 to prevent abuse)
     *   ?q=floor         Search term for name / description
     *
     * Legacy mirror: ?action=list_layouts
     */
    public function index(Request $request): JsonResponse
    {
        $page     = max(1, (int) $request->query('page', 1));
        $pageSize = min(100, max(1, (int) $request->query('page_size', 10)));
        $search   = trim((string) $request->query('q', ''));

        $result = $this->layoutService->list($page, $pageSize, $search);

        return response()->json($result);
    }

    /*
    |--------------------------------------------------------------------------
    | m=show — Single layout with full layout_json (PUBLIC)
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=FactoryLayout&m=show&id=1
     *
     * Legacy mirror: ?action=get_layout&id=N
     *
     * Returns the full payload including the parsed layout_json array.
     */
    public function show(Request $request): JsonResponse
    {
        $id = (int) $request->query('id', 0);

        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'id is required.'], 400);
        }

        try {
            $layout = $this->layoutService->get($id);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Layout not found.'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => $layout->toDetailArray(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=store — Create or update a layout (ADMIN ONLY)
    |--------------------------------------------------------------------------
    */

    /**
     * POST /api/gateway?c=FactoryLayout&m=store
     *
     * Body: { id?, name, description?, layout_json, canvas_w?, canvas_h? }
     *   id=null  → CREATE  (created_by set from JWT username)
     *   id=N     → UPDATE  (created_by unchanged)
     *
     * Legacy mirror: ?action=save_layout
     */
    public function store(SaveLayoutRequest $request): JsonResponse
    {
        // Auth gate — must be admin
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        try {
            $layout = $this->layoutService->save($request->validated(), $who);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Layout not found.'], 404);
        }

        return response()->json([
            'status'  => 'ok',
            'message' => 'Saved.',
            'data'    => $layout->toDetailArray(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=destroy — Delete a layout (ADMIN + OWNERSHIP)
    |--------------------------------------------------------------------------
    */

    /**
     * POST|DELETE /api/gateway?c=FactoryLayout&m=destroy
     *
     * Body: { id: N }
     *
     * Business rules:
     *   - Caller must be admin (401/403 on failure).
     *   - layout.created_by must equal JWT username (403 on mismatch).
     *
     * Legacy mirror: ?action=delete_layout
     */
    public function destroy(Request $request): JsonResponse
    {
        // Auth gate — must be admin
        $claims = $this->requireRole($request, ['admin']);
        $who    = $this->whoFromClaims($claims);

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            return response()->json(['status' => 'error', 'message' => 'id is required.'], 400);
        }

        try {
            $this->layoutService->delete($id, $who);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Layout not found.'], 404);
        } catch (AuthorizationException $e) {
            // Ownership mismatch — mirrors legacy:
            //   respond_json_error('permission denied', 403)
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'ok', 'message' => 'Deleted.']);
    }
}
