<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * DeviceHistoryRequest
 *
 * Validates parameters for the c=Device&m=history gateway action.
 *
 * Legacy mirror: ?action=search_device&device_id=MOLD_01&from=...&to=...
 */
class DeviceHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'device_id' => ['required', 'string', 'max:50'],
            'from'      => ['nullable', 'date'],
            'to'        => ['nullable', 'date', 'after_or_equal:from'],
            'limit'     => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.required' => 'device_id is required.',
            'to.after_or_equal'  => 'to must be on or after from.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TYPED ACCESSORS
    |--------------------------------------------------------------------------
    */

    public function getDeviceId(): string
    {
        return (string) $this->validated('device_id');
    }

    public function getFrom(): ?string
    {
        $v = $this->validated('from');
        return $v !== null ? (string) $v : null;
    }

    public function getTo(): ?string
    {
        $v = $this->validated('to');
        return $v !== null ? (string) $v : null;
    }

    public function getLimit(): int
    {
        return min(1000, max(1, (int) ($this->validated('limit') ?? 500)));
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => collect($validator->errors()->all())->first() ?? 'Validation failed.',
            ], 400)
        );
    }
}
