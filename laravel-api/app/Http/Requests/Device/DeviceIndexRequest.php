<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * DeviceIndexRequest
 *
 * Validates optional filter params for c=Device&m=index.
 * Legacy mirror: ?action=get_devices
 */
class DeviceIndexRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'page'         => ['nullable', 'integer', 'min:1'],
            'page_size'    => ['nullable', 'integer', 'min:1', 'max:100'],
            'q'            => ['nullable', 'string', 'max:255'],
            'display_type' => ['nullable', 'string', 'in:mold,tuft,blister'],
            'process'      => ['nullable', 'string', 'max:50'],
        ];
    }

    public function getPage(): int      { return max(1, (int) ($this->validated('page') ?? 1)); }
    public function getPageSize(): int  { return min(100, max(1, (int) ($this->validated('page_size') ?? 20))); }
    public function getSearch(): string { return trim((string) ($this->validated('q') ?? '')); }
    public function getType(): string   { return trim((string) ($this->validated('display_type') ?? '')); }
    public function getProcess(): string { return trim((string) ($this->validated('process') ?? '')); }
}
