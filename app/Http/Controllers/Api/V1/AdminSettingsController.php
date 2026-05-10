<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminSettingsUpdateRequest;
use App\Services\AdminSettingsService;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\AdminSettingsPresenter;
use Illuminate\Http\JsonResponse;

final class AdminSettingsController extends Controller
{
    public function __construct(private readonly AdminSettingsService $settings) {}

    public function show(): JsonResponse
    {
        return ApiResponse::data(AdminSettingsPresenter::present(
            settings: $this->settings->getRecord(),
            service: $this->settings,
        ));
    }

    public function update(AdminSettingsUpdateRequest $request): JsonResponse
    {
        $record = $this->settings->persistNewsAutoRefreshDefaults(
            enabled: $request->boolean('news_auto_refresh_enabled'),
            seconds: $request->integer('news_auto_refresh_interval_seconds'),
        );

        return ApiResponse::data(AdminSettingsPresenter::present($record, $this->settings));
    }
}
