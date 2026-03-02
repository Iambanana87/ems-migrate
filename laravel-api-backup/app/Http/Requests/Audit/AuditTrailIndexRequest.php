<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * AuditTrailIndexRequest
 *
 * Validates optional filter parameters for the c=AuditTrail&m=index endpoint.
 *
 * All fields are optional — an empty request returns the full paginated list.
 * No failedValidation() override is needed: these are soft filter params
 * and a validation error (e.g. invalid sort value) returns Laravel's default
 * 422 shape, which is acceptable for query-string validation in a JSON API.
 *
 * Legacy mirror: ?mode=list filters in model/get_audit_trail.php
 */
class AuditTrailIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Any authenticated user may read the audit trail.
        // Auth enforcement is handled in AuditTrailController via RequiresAuth.
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page'      => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:100'],

            // Exact-match filters
            'type'      => ['nullable', 'string', 'max:100'],
            'who'       => ['nullable', 'string', 'max:100'],

            // LIKE search across type + who columns
            'q'         => ['nullable', 'string', 'max:255'],

            // Date-range filters — accepts any date/datetime string MySQL understands
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date'],

            // Sort direction for created_at ordering
            'sort'      => ['nullable', 'in:asc,desc'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TYPED ACCESSORS
    |--------------------------------------------------------------------------
    | Provide clean, typed getters so the controller never touches raw arrays.
    */

    public function getPage(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    public function getPageSize(): int
    {
        return min(100, max(1, (int) ($this->validated('page_size') ?? 20)));
    }

    public function getType(): string
    {
        return trim((string) ($this->validated('type') ?? ''));
    }

    public function getWho(): string
    {
        return trim((string) ($this->validated('who') ?? ''));
    }

    public function getSearch(): string
    {
        return trim((string) ($this->validated('q') ?? ''));
    }

    public function getFrom(): string
    {
        return trim((string) ($this->validated('from') ?? ''));
    }

    public function getTo(): string
    {
        return trim((string) ($this->validated('to') ?? ''));
    }

    public function getSort(): string
    {
        $sort = strtolower(trim((string) ($this->validated('sort') ?? 'desc')));
        return in_array($sort, ['asc', 'desc'], strict: true) ? $sort : 'desc';
    }
}
