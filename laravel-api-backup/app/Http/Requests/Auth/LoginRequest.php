<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * LoginRequest
 *
 * Validates credentials for the c=Auth&m=login gateway action.
 *
 * Rules mirror legacy validateInput() in backend/login.php exactly:
 *   - username: 4–20 chars, alphanumeric + underscore only
 *   - password: minimum 8 characters
 *   - otp:      optional string (passed through to IAM)
 *
 * The request body may arrive as JSON (application/json) or form-encoded
 * (application/x-www-form-urlencoded) — both formats are supported by
 * the Gateway because it merges php://input into $request->all() when
 * Content-Type is application/json.
 */
class LoginRequest extends FormRequest
{
    /**
     * No additional authorization check here.
     * The login endpoint is public by definition.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            // Legacy: strlen($username) < 4 || strlen($username) > 20
            //         || !preg_match('/^[a-zA-Z0-9_]+$/', $username)
            'username' => [
                'required',
                'string',
                'min:4',
                'max:20',
                'regex:/^[a-zA-Z0-9_]+$/',
            ],

            // Legacy: strlen($password) < 8
            'password' => [
                'required',
                'string',
                'min:8',
            ],

            // OTP: optional. Sent as-is to the IAM service.
            'otp' => [
                'nullable',
                'string',
                'max:16',
            ],
        ];
    }

    /**
     * Human-readable error messages that mirror the legacy Vietnamese/English
     * error strings shown in the HTML login form.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'username.min'      => 'Username phải 4–20 ký tự và chỉ gồm chữ/số/gạch dưới.',
            'username.max'      => 'Username phải 4–20 ký tự và chỉ gồm chữ/số/gạch dưới.',
            'username.regex'    => 'Username phải 4–20 ký tự và chỉ gồm chữ/số/gạch dưới.',
            'password.required' => 'Password is required.',
            'password.min'      => 'Mật khẩu phải ít nhất 8 ký tự.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | TYPED ACCESSORS
    |--------------------------------------------------------------------------
    | Provide clean, typed getters so the controller never touches raw arrays.
    */

    public function getUsername(): string
    {
        return (string) $this->validated('username');
    }

    public function getPassword(): string
    {
        return (string) $this->validated('password');
    }

    public function getOtp(): string
    {
        return (string) ($this->validated('otp') ?? '');
    }

    /*
    |--------------------------------------------------------------------------
    | VALIDATION FAILURE — override default 422 shape
    |--------------------------------------------------------------------------
    | Legacy: respond_json_error($validationMessage, 400)
    |   → { "status": "error", "message": "<first error string>" }
    |
    | Without this override, Laravel returns a 422 with an `errors` object —
    | which differs from what the frontend already handles.
    */
    protected function failedValidation(Validator $validator): never
    {
        $message = collect($validator->errors()->all())->first() ?? 'Validation failed.';

        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => $message,
            ], 400)
        );
    }
}
