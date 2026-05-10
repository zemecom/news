<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class AdminAiProviderAccountUpdateRequest extends FormRequest
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
            'display_name' => ['sometimes', 'string', 'max:120'],
            'is_enabled' => ['sometimes', 'boolean'],
            'default_model' => ['sometimes', 'string', 'max:120'],
            'default_reasoning_effort' => ['sometimes', 'nullable', 'string', 'max:20'],
            'max_parallel_jobs' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ];
    }
}
