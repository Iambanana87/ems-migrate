<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * CreateDeviceActionRequest
 * Legacy mirror: ?action=action_create
 */
class CreateDeviceActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id'  => ['required', 'string', 'max:50', 'exists:devices,device_id'],
            'title'      => ['required', 'string', 'max:100'],
            'short_form' => ['nullable', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.exists' => 'Device not found.',
            'title.required'   => 'Action label is required.',
        ];
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['error' => collect($validator->errors()->all())->first()], 500)
        );
    }
}
