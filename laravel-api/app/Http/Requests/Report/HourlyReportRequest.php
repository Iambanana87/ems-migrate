<?php

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

class HourlyReportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'device_id' => 'required|string',
            'report_date' => 'nullable|date_format:Y-m-d',
        ];
    }
}
