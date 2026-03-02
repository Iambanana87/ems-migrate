<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * UpdateDeviceStatusRequest
 *
 * Validates a manual override of connection_status or threshold_status.
 * Legacy mirror: ?action=update_device_status
 */
class UpdateDeviceStatusRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'device_id'          => ['required', 'string', 'max:50', 'exists:devices,device_id'],
            'connection_status'  => ['nullable', 'string', 'in:Connected,Disconnected'],
            'threshold_status'   => ['nullable', 'string', 'in:Normal,Breached'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'device_id.exists' => 'Device not found.',
        ];
    }

    public function getDeviceId(): string         { return (string) $this->validated('device_id'); }
    public function getConnectionStatus(): ?string { return ($v = $this->validated('connection_status')) !== null ? (string) $v : null; }
    public function getThresholdStatus(): ?string  { return ($v = $this->validated('threshold_status')) !== null ? (string) $v : null; }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
