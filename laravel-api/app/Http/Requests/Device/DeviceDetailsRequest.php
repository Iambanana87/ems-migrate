<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * DeviceDetailsRequest
 *
 * Validates the required device_id parameter for the
 * c=Device&m=details gateway action.
 *
 * Legacy mirror: ?action=get_machine_details&device_id=MOLD_01
 */
class DeviceDetailsRequest extends FormRequest
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
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.required' => 'device_id is required.',
        ];
    }

    public function getDeviceId(): string
    {
        return (string) $this->validated('device_id');
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
