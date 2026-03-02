<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

class UpdateActionPlanStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Using loose inputs exactly as legacy
            'plan_id' => ['nullable'], // falls back to 0 natively
            'status'  => ['nullable'], // mapped via inline logic
            'reason'  => ['nullable'], // trimmed manually
        ];
    }
}
