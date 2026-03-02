<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class EfficiencyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'process'    => ['nullable', 'string'],
            'from'       => ['nullable', 'string'],
            'to'         => ['nullable', 'string'],
            'sort_by'    => ['nullable', 'string'],
            'sort_order' => ['nullable', 'string'],
        ];
    }
}
