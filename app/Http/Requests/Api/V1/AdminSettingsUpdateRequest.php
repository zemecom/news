<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\AdminSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdminSettingsUpdateRequest extends FormRequest
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
        $allowedSeconds = array_keys(app(AdminSettingsService::class)->newsAutoRefreshIntervalSecondsOptions());

        return [
            'news_auto_refresh_enabled' => ['required', 'boolean'],
            'news_auto_refresh_interval_seconds' => ['required', 'integer', Rule::in($allowedSeconds)],
        ];
    }
}
