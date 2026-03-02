<?php

declare(strict_types=1);

namespace App\Http\Requests\DeviceAction;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * UpdatePlanOrderRequest
 *
 * Validates the batch priority payload from the Kanban drag-and-drop board.
 * Legacy mirror: ?action=update_plan_order
 *
 * Expected body: { "items": [{ "id": 1, "priority": 0 }, ...] }
 */
class UpdatePlanOrderRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'items'          => ['required', 'array', 'min:1'],
            'items.*.id'     => ['required', 'integer', 'exists:device_actions,id'],
            'items.*.priority' => ['required', 'integer', 'min:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'        => 'items array is required.',
            'items.*.id.exists'     => 'One or more action IDs are invalid.',
            'items.*.priority.required' => 'Each item must include a priority.',
        ];
    }

    /**
     * @return list<array{id:int, priority:int}>
     */
    public function getItems(): array
    {
        return collect($this->validated('items'))
            ->map(fn ($item) => ['id' => (int) $item['id'], 'priority' => (int) $item['priority']])
            ->values()
            ->all();
    }

    protected function failedValidation(Validator $validator): never
    {
        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => collect($validator->errors()->all())->first()], 400)
        );
    }
}
