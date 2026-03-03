<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use Illuminate\Foundation\Http\FormRequest;

final class ListBackendIssuesRequest extends FormRequest
{
    /**
     * Legacy api.php uses $_GET['display_type'] (default 'all').
     */
    public function rules(): array
    {
        return [
            'display_type' => ['nullable', 'string'],
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
