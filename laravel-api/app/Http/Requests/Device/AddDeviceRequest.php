<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * AddDeviceRequest
 *
 * Validates the full device config payload for c=Device&m=store.
 * device_id must be unique. Type-specific required fields are enforced
 * with required_if rules.
 *
 * Legacy mirror: ?action=add
 */
class AddDeviceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id'    => ['required', 'string', 'max:50', 'unique:devices,device_id'],
            'display_type' => ['required', 'string', 'in:mold,tuft,blister'],
            'process'      => ['nullable', 'string', 'max:50'],
            'product'      => ['nullable', 'string', 'max:100'],
            'model'        => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'manufacturing_date' => ['nullable', 'date'],
            'target_limit' => ['required', 'numeric', 'min:0'],
            'lower_limit'  => ['nullable', 'numeric', 'min:0'],
            'upper_limit'  => ['nullable', 'numeric', 'min:0'],
            'efficiency_lower_limit' => ['nullable', 'numeric'],
            'efficiency_upper_limit' => ['nullable', 'numeric'],
            // mold-specific
            'cavities'     => ['required_if:display_type,mold', 'nullable', 'integer', 'min:1'],
            'mold_type'    => ['nullable', 'string', 'max:110'],
            // blister-specific
            'brushes_per_cycle' => ['required_if:display_type,blister', 'nullable', 'integer', 'min:1'],
            // tuft-specific
            'hole_per_brush' => ['nullable', 'integer', 'min:1'],
            // general
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
            'device_id.unique'             => 'A device with this ID already exists.',
            'display_type.in'              => 'display_type must be mold, tuft, or blister.',
            'cavities.required_if'         => 'cavities is required for mold devices.',
            'brushes_per_cycle.required_if' => 'brushes_per_cycle is required for blister devices.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
