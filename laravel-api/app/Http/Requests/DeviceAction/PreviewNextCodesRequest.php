<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewNextCodesRequest extends FormRequest
{
    /**
     * Legacy preview_next_codes requires no specific parameters.
     */
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        return true;
    }
}
