<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AdminSourceStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'url' => ['required', 'url', 'max:2048'],
            'type' => ['required', 'string', 'max:32'],
            'language_default' => ['nullable', 'string', 'max:16'],
            'cron_expression' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'retry_backoff_state' => ['nullable', 'array'],
            'last_success_at' => ['nullable', 'date'],
            'last_error_at' => ['nullable', 'date'],
            'error_streak' => ['required', 'integer', 'min:0'],
        ];
    }
}
