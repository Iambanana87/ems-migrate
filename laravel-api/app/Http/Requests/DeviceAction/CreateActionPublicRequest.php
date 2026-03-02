<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

class CreateActionPublicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Structural Hardening: Validating foreign key existence before mutation
            'device_id'  => ['required', 'string', 'exists:devices,device_id'],
            'title'      => ['required', 'string'],
            'short_form' => ['required', 'json'],
            'issue_type' => ['nullable', 'string'],
        ];
    }
}
