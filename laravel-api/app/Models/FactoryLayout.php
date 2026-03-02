<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * FactoryLayout Eloquent Model
 *
 * Represents a saved factory floor plan in the `factory_layouts` table
 * (production database). Created and updated by authenticated admins via
 * the drawLayout.php editor; read by the viewLayout.php viewer.
 *
 * @property int             $id
 * @property string          $name
 * @property string|null     $description
 * @property array           $layout_json   Auto-decoded from JSON string
 * @property int|null        $canvas_w
 * @property int|null        $canvas_h
 * @property string|null     $created_by    JWT username of creator (denormalised)
 * @property \Carbon\Carbon  $created_at
 * @property \Carbon\Carbon  $updated_at
 */
class FactoryLayout extends Model
{
    use HasFactory;

    protected $table = 'factory_layouts';

    /*
    |--------------------------------------------------------------------------
    | TIMESTAMPS
    |--------------------------------------------------------------------------
    | Both created_at and updated_at exist in the table.
    | The DB handles updated_at via ON UPDATE current_timestamp() trigger.
    | We keep Laravel's timestamp management active so Eloquent also writes
    | updated_at correctly on save() calls.
    */
    public $timestamps = true;

    /*
    |--------------------------------------------------------------------------
    | MASS ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'layout_json',
        'canvas_w',
        'canvas_h',
        'created_by',  // set by FactoryLayoutService from JWT claims — never from client input
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    | KEY REQUIREMENT: layout_json → array
    |   On read:  JSON string from DB is auto-decoded → PHP array
    |   On write: PHP array is auto-encoded → JSON string stored in DB
    |
    | This means FactoryLayoutController::show() returns layout_json as a
    | parsed array in the JSON response — matching the legacy behaviour of
    | json_decode($row['layout_json'], true) before echo json_encode().
    */

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id'          => 'integer',
            'layout_json' => 'array',      // ← critical: auto JSON encode/decode
            'canvas_w'    => 'integer',
            'canvas_h'    => 'integer',
            'created_at'  => 'datetime',
            'updated_at'  => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | QUERY SCOPES
    |--------------------------------------------------------------------------
    */

    /**
     * Scope: search by name or description (case-insensitive LIKE).
     *
     * Legacy: WHERE name LIKE '%q%' OR description LIKE '%q%'
     *
     * @param  Builder<FactoryLayout>  $query
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $q->where('name', 'LIKE', "%{$term}%")
              ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    /*
    |--------------------------------------------------------------------------
    | LIST SERIALISATION (excludes layout_json for performance)
    |--------------------------------------------------------------------------
    | When returning a list of layouts, layout_json is excluded — it can be
    | hundreds of KB. The `show` endpoint returns the full payload.
    */

    /** @var list<string> */
    protected $hidden = [];

    /**
     * Return the model array WITHOUT layout_json — for list responses.
     *
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return $this->only([
            'id',
            'name',
            'description',
            'canvas_w',
            'canvas_h',
            'created_by',
            'created_at',
            'updated_at',
        ]);
    }

    /**
     * Return the full model array INCLUDING layout_json — for show/save responses.
     *
     * @return array<string, mixed>
     */
    public function toDetailArray(): array
    {
        return $this->only([
            'id',
            'name',
            'description',
            'layout_json',   // decoded array (via cast)
            'canvas_w',
            'canvas_h',
            'created_by',
            'created_at',
            'updated_at',
        ]);
    }
}
