<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ActionIdRequest
 *
 * Minimal request carrying only an action id.
 * Shared by approve and destroy actions.
 */
class ActionIdRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id'    => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['id.exists' => 'Action not found.'];
    }

    public function getId(): int { return (int) $this->validated('id'); }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
