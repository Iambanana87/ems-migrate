<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class OutputReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'process' => 'nullable|string|in:mold,tuft,blister',
            'family'  => 'nullable|string',
            'from'    => 'nullable|date_format:Y-m-d\TH:i:s',
            'to'      => 'nullable|date_format:Y-m-d\TH:i:s',
        ];
    }
}
