<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * UpdateDeviceRequest
 *
 * Validates the update payload for c=Device&m=update.
 * device_id identifies which record to update; all config fields are optional.
 *
 * Legacy mirror: ?action=update
 */
class UpdateDeviceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id'    => ['required', 'string', 'max:50', 'exists:devices,device_id'],
            'reason'       => ['nullable', 'string', 'max:255'],  // audit reason note
            'process'      => ['nullable', 'string', 'max:50'],
            'product'      => ['nullable', 'string', 'max:100'],
            'model'        => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'target_limit' => ['nullable', 'numeric', 'min:0'],
            'lower_limit'  => ['nullable', 'numeric', 'min:0'],
            'upper_limit'  => ['nullable', 'numeric', 'min:0'],
            'efficiency_lower_limit' => ['nullable', 'numeric'],
            'efficiency_upper_limit' => ['nullable', 'numeric'],
            'cavities'     => ['nullable', 'integer', 'min:1'],
            'mold_type'    => ['nullable', 'string', 'max:110'],
            'brushes_per_cycle' => ['nullable', 'integer', 'min:1'],
            'hole_per_brush' => ['nullable', 'integer', 'min:1'],
            'frequency'    => ['nullable', 'integer', 'min:5'],
            'freq_check_limit' => ['nullable', 'integer', 'min:60'],
            'history_count' => ['nullable', 'integer', 'min:1'],
            'flex'         => ['nullable', 'boolean'],
            'client'       => ['nullable', 'string', 'max:50'],
            'unit'         => ['nullable', 'string', 'max:16'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.exists' => 'Device not found.',
        ];
    }

    /**
     * Extract only the updatable fields (exclude device_id and reason)
     * for passing to DeviceService::updateDevice().
     *
     * @return array<string, mixed>
     */
    public function updateData(): array
    {
        return collect($this->validated())
            ->except(['device_id', 'reason'])
            ->filter(fn ($v) => $v !== null)
            ->all();
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
