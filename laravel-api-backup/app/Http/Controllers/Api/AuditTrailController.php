<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\RequiresAuth;
use App\Http\Requests\Audit\AuditTrailIndexRequest;
use App\Models\AuditTrail;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * AuditTrailController
 *
 * Read-only controller for audit log data, dispatched by the Gateway:
 *   c=AuditTrail & m=index → index()   [any authenticated user]
 *   c=AuditTrail & m=show  → show()    [any authenticated user]
 *
 * No write operations here — `AuditService::log()` is the write path,
 * called internally by mutating services (DeviceService, etc.).
 *
 * Legacy mirror: model/get_audit_trail.php (?mode=list / ?mode=one)
 */
class AuditTrailController extends Controller
{
    use RequiresAuth;

    /*
    |--------------------------------------------------------------------------
    | m=index — Paginated filtered list
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=AuditTrail&m=index
     *
     * Optional query params (all validated by AuditTrailIndexRequest):
     *   ?page, ?page_size, ?type, ?who, ?q, ?from, ?to, ?sort
     *
     * Legacy mirror: ?mode=list in model/get_audit_trail.php
     *
     * Response:
     *   { status, total, page, page_size, total_pages, items[] }
     */
    public function index(AuditTrailIndexRequest $request): JsonResponse
    {
        // Auth gate — any authenticated user (not role-specific)
        $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));

        $page     = $request->getPage();
        $pageSize = $request->getPageSize();

        // Build filtered query using model scopes
        $query = AuditTrail::query();

        // Exact filters — applied only when param is non-empty
        if (($type = $request->getType()) !== '') {
            $query->ofType($type);
        }

        if (($who = $request->getWho()) !== '') {
            $query->byWho($who);
        }

        // LIKE search across type OR who
        if (($search = $request->getSearch()) !== '') {
            $query->search($search);
        }

        // Date-range filters
        if (($from = $request->getFrom()) !== '') {
            $query->from($from);
        }

        if (($to = $request->getTo()) !== '') {
            $query->to($to);
        }

        // Sort direction (default: DESC — newest first)
        $query->orderBy('created_at', $request->getSort());

        // Count before pagination (avoids COUNT(*) re-running on the full result)
        $total      = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;

        $rows = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // Serialise — created_at as formatted string to match legacy output
        $items = $rows->map(fn (AuditTrail $row) => [
            'id'         => $row->id,
            'type'       => $row->type,
            'reason'     => $row->reason,
            'who'        => $row->who,
            'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
            'diff'       => $row->diff, // already decoded array via cast
        ])->values()->all();

        return response()->json([
            'status'      => 'ok',
            'total'       => $total,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_pages' => $totalPages,
            'items'       => $items,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | m=show — Single record by UUID
    |--------------------------------------------------------------------------
    */

    /**
     * GET /api/gateway?c=AuditTrail&m=show&id=<uuid>
     *
     * Legacy mirror: ?mode=one&id=<uuid> in model/get_audit_trail.php
     *
     * Response:
     *   { status, data: { id, type, reason, who, created_at, diff } }
     */
    public function show(Request $request): JsonResponse
    {
        // Auth gate — any authenticated user
        $this->requireRole($request, array_values((array) config('ems.role_hierarchy')));

        $id = trim((string) $request->query('id', ''));

        if ($id === '') {
            return response()->json(['status' => 'error', 'message' => 'id is required.'], 400);
        }

        try {
            $row = AuditTrail::findOrFail($id);
        } catch (ModelNotFoundException) {
            return response()->json(['status' => 'error', 'message' => 'Record not found.'], 404);
        }

        return response()->json([
            'status' => 'ok',
            'data'   => [
                'id'         => $row->id,
                'type'       => $row->type,
                'reason'     => $row->reason,
                'who'        => $row->who,
                'created_at' => $row->created_at?->format('Y-m-d H:i:s'),
                'diff'       => $row->diff,
            ],
        ]);
    }
}
