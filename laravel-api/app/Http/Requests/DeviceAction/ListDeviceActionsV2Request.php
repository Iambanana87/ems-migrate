<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

class ListDeviceActionsV2Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Following Phase 2 restrictions, no silent fallbacks. Use strict rules.
            'device_id' => ['required', 'string'],
        ];
    }
}
