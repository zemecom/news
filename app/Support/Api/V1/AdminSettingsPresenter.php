<?php

declare(strict_types=1);

namespace App\Support\Api\V1;

use App\Models\AdminSetting;
use App\Services\AdminSettingsService;

final class AdminSettingsPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(AdminSetting $settings, AdminSettingsService $service): array
    {
        return [
            'news_auto_refresh_enabled' => $settings->news_auto_refresh_enabled,
            'news_auto_refresh_interval_seconds' => $settings->news_auto_refresh_interval_seconds,
            'news_auto_refresh_selection_options' => $service->newsAutoRefreshSelectionOptions(),
        ];
    }
}
