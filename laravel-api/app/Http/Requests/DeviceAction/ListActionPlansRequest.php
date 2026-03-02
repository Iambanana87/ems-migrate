<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

class ListActionPlansRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Using nullable because legacy casts null/missing to 0 and returns []
            // We must allow null or string or int to flow to the controller to preserve the exact `echo json_encode([])` behavior.
            'action_id' => ['nullable'],
        ];
    }
}
