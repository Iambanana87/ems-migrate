<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Foundation\Http\FormRequest;

final class BulkUpdateActionPlanStatusRequest extends FormRequest
{
    /**
     * Legacy api.php uses $_POST['action_id'] and $_POST['status'].
     * status defaults to 'done' and is forced to 'open' if 'open' is passed, else 'done'.
     */
    public function rules(): array
    {
        return [
            'action_id' => ['required', 'integer'],
            'status'    => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
