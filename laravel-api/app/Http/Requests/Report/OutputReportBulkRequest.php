<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class OutputReportBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from'     => ['required', 'string'],
            'to'       => ['required', 'string'],
            'process'  => ['nullable', 'string'],
            'families' => ['nullable', 'string'],
            'products' => ['nullable', 'string'],
        ];
    }
}
