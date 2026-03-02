<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class DevicesActionTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Keep validation permissive per user's strict architectural requirement.
        // Fallback checks will happen inside the Service layer exactly like legacy PHP.
        return [
            'display_type' => ['nullable', 'string'],
        ];
    }
}
