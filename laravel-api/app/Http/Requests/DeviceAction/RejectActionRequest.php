<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * RejectActionRequest
 *
 * Carries the action id and mandatory rejection reason.
 * Legacy mirror: ?action=reject_action
 */
class RejectActionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id'    => ['required', 'integer'],
            'notes' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'id.exists'      => 'Action not found.',
            'notes.required' => 'A rejection reason is required',
        ];
    }

    public function getId(): int         { return (int) $this->validated('id'); }
    public function getNotes(): string   { return (string) $this->validated('notes'); }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
