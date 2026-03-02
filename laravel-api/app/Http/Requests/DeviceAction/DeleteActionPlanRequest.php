<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

class DeleteActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Following legacy fallback logic exactly: (int)($_POST['plan_id'] ?? 0)
            'plan_id' => ['nullable'],
            // Following legacy logic: trim((string)($_POST['reason'] ?? ''))
            'reason'  => ['nullable'],
        ];
    }
}
