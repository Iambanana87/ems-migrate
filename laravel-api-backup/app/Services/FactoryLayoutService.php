<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FactoryLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * FactoryLayoutService
 *
 * Encapsulates all business logic from legacy layout_api.php:
 *   - Paginated list with full-text search
 *   - Create / update (upsert) with layout_json validation
 *   - Single-record fetch
 *   - Ownership-checked delete
 */
final class FactoryLayoutService
{
    /*
    |--------------------------------------------------------------------------
    | LIST
    |--------------------------------------------------------------------------
    */

    /**
     * Return a paginated list of layouts — layout_json EXCLUDED for performance.
     *
     * Legacy action: ?action=list_layouts
     *   SELECT id, name, description, canvas_w, canvas_h, created_by,
     *          created_at, updated_at
     *   FROM factory_layouts
     *   WHERE (name LIKE '%q%' OR description LIKE '%q%')
     *   ORDER BY updated_at DESC
     *   LIMIT page_size OFFSET (page-1)*page_size
     *
     * Response shape:
     *   { status, total, page, page_size, total_pages, items[] }
     *
     * @return array{status:string, total:int, page:int, page_size:int, total_pages:int, items:list<array<string,mixed>>}
     */
    public function list(int $page, int $pageSize, string $search = ''): array
    {
        $query = FactoryLayout::query()
            ->search($search)
            ->orderByDesc('updated_at');

        $total      = $query->count();
        $totalPages = $pageSize > 0 ? (int) ceil($total / $pageSize) : 1;

        $rows = $query
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get();

        // toListArray() deliberately excludes layout_json
        $items = $rows->map(fn (FactoryLayout $l) => $l->toListArray())->values()->all();

        return [
            'status'      => 'ok',
            'total'       => $total,
            'page'        => $page,
            'page_size'   => $pageSize,
            'total_pages' => $totalPages,
            'items'       => $items,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | SAVE (CREATE OR UPDATE)
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new layout or update an existing one.
     *
     * Legacy action: ?action=save_layout
     * Business rules:
     *   - If `id` is present + exists → UPDATE (created_by is NOT changed).
     *   - If `id` is absent or null   → INSERT (created_by = $who).
     *   - layout_json is already validated as an array by SaveLayoutRequest.
     *     The model cast handles JSON encoding on save automatically.
     *
     * @param  array<string, mixed>  $data  Validated data from SaveLayoutRequest.
     * @param  string                $who   JWT username of the acting admin.
     *
     * @throws ModelNotFoundException  if `id` provided but not found in DB.
     */
    public function save(array $data, string $who): FactoryLayout
    {
        $id = isset($data['id']) && $data['id'] !== null ? (int) $data['id'] : null;

        if ($id !== null) {
            // UPDATE path — find or 404
            $layout = FactoryLayout::findOrFail($id);
        } else {
            // CREATE path — new model, set creator
            $layout = new FactoryLayout();
            $layout->created_by = $who;
        }

        // Apply fields common to both create and update
        $layout->name        = $data['name'];
        $layout->description = $data['description'] ?? null;
        $layout->layout_json = $data['layout_json'];   // array → auto JSON-encoded by cast
        $layout->canvas_w    = isset($data['canvas_w']) ? (int) $data['canvas_w'] : null;
        $layout->canvas_h    = isset($data['canvas_h']) ? (int) $data['canvas_h'] : null;

        $layout->save();

        return $layout->fresh(); // ensure casts + timestamps are populated
    }

    /*
    |--------------------------------------------------------------------------
    | GET (SINGLE)
    |--------------------------------------------------------------------------
    */

    /**
     * Fetch a single layout by primary key — including layout_json.
     *
     * Legacy action: ?action=get_layout&id=N
     *
     * @throws ModelNotFoundException  if not found (→ 404 in controller).
     */
    public function get(int $id): FactoryLayout
    {
        // findOrFail throws ModelNotFoundException automatically
        return FactoryLayout::findOrFail($id);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */

    /**
     * Delete a layout after verifying the requesting user owns it.
     *
     * Legacy action: ?action=delete_layout
     * Business rules:
     *   - Requires admin role (enforced in controller before calling this).
     *   - `created_by` of the layout must equal the requesting user's username.
     *   - Throws AuthorizationException (→ 403) on ownership mismatch.
     *   - Throws ModelNotFoundException (→ 404) if id not found.
     *
     * @throws ModelNotFoundException   if layout does not exist.
     * @throws AuthorizationException   if requester does not own the layout.
     */
    public function delete(int $id, string $who): void
    {
        $layout = FactoryLayout::findOrFail($id);

        // Ownership check — mirrors legacy:
        //   if ($layout['created_by'] !== $WHO) { respond_json_error(403) }
        if ($layout->created_by !== $who) {
            throw new AuthorizationException(
                "Permission denied: layout owned by '{$layout->created_by}'."
            );
        }

        $layout->delete();
    }
}
