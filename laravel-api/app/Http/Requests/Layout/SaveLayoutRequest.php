<?php

declare(strict_types=1);

namespace App\Http\Requests\Layout;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * SaveLayoutRequest
 *
 * Validates input for the c=FactoryLayout&m=store gateway action.
 *
 * Handles both CREATE (id=null) and UPDATE (id=integer) in one request class,
 * matching the legacy save_layout action which used a single endpoint for both.
 *
 * The layout_json field is special: the frontend may send it as:
 *   a) An array  (when Content-Type: application/json → PHP decodes it)
 *   b) A JSON string (rare legacy path)
 * Either form is accepted; passedValidation() normalises it to an array
 * so FactoryLayoutService always receives a consistent type.
 */
class SaveLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Role enforcement is handled in FactoryLayoutController via RequiresAuth trait.
        // FormRequest authorization is not the right place for JWT checks.
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // id: null → create, integer → update.
            // exists rule ensures we don't silently create if an invalid id is passed.
            'id' => [
                'nullable',
                'integer',
                'exists:factory_layouts,id',
            ],

            // Mirroring legacy: name is required for both create and update.
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:500',
            ],

            // layout_json: required — accepts either a PHP array (JSON-decoded by
            // Laravel from a JSON request body) or a raw JSON string.
            'layout_json' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_array($value)) {
                        return; // Already decoded — valid
                    }
                    if (! is_string($value)) {
                        $fail('layout_json must be a JSON string or array.');
                        return;
                    }
                    json_decode($value);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $fail('layout_json contains invalid JSON: ' . json_last_error_msg());
                    }
                },
            ],

            'canvas_w' => ['nullable', 'integer', 'min:1'],
            'canvas_h' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required'        => 'Layout name is required.',
            'layout_json.required' => 'layout_json is required.',
            'id.exists'            => 'Layout not found.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | POST-VALIDATION NORMALISATION
    |--------------------------------------------------------------------------
    | Ensure layout_json is always a PHP array when it reaches the service,
    | regardless of whether the client sent an array or a JSON string.
    */

    protected function passedValidation(): void
    {
        $layoutJson = $this->input('layout_json');

        if (is_string($layoutJson)) {
            // Decode JSON string → array for consistent service layer input
            $this->merge(['layout_json' => json_decode($layoutJson, associative: true)]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | FAILURE — match legacy { status:"error", message:"..." } shape
    |--------------------------------------------------------------------------
    */

    protected function failedValidation(Validator $validator): never
    {
        $message = collect($validator->errors()->all())->first() ?? 'Validation failed.';

        throw new HttpResponseException(
            response()->json(['status' => 'error', 'message' => $message], 400)
        );
    }
}
