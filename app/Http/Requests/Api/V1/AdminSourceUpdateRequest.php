<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AdminSourceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'type' => ['sometimes', 'string', 'max:32'],
            'language_default' => ['sometimes', 'nullable', 'string', 'max:16'],
            'cron_expression' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'retry_backoff_state' => ['sometimes', 'nullable', 'array'],
            'last_success_at' => ['sometimes', 'nullable', 'date'],
            'last_error_at' => ['sometimes', 'nullable', 'date'],
            'error_streak' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
