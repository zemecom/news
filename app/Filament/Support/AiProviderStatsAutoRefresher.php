<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Throwable;

final readonly class AiProviderStatsAutoRefresher
{
    public function __construct(
        private SyncAiProviderStatsAction $syncStats,
    ) {}

    public function refreshIfStale(
        string $provider = AiProviderAccount::PROVIDER_CHATGPT_CODEX,
        int $maxAgeMinutes = 5,
    ): bool {
        if (! $this->hasStaleAccounts($provider, $maxAgeMinutes)) {
            return false;
        }

        try {
            $this->syncStats->run($provider);
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    public function scheduleRefreshIfStale(
        string $provider = AiProviderAccount::PROVIDER_CHATGPT_CODEX,
        int $maxAgeMinutes = 5,
    ): bool {
        if (! $this->hasStaleAccounts($provider, $maxAgeMinutes)) {
            return false;
        }

        $cacheKey = $this->refreshStateCacheKey($provider);

        if (! Cache::add($cacheKey, true, 120)) {
            return true;
        }

        app()->terminating(function () use ($provider, $cacheKey): void {
            try {
                $this->syncStats->run($provider);
            } catch (Throwable $e) {
                report($e);
            } finally {
                Cache::forget($cacheKey);
            }
        });

        return true;
    }

    public function isRefreshing(string $provider = AiProviderAccount::PROVIDER_CHATGPT_CODEX): bool
    {
        return Cache::has($this->refreshStateCacheKey($provider));
    }

    private function hasStaleAccounts(string $provider, int $maxAgeMinutes): bool
    {
        $staleThreshold = now()->subMinutes($maxAgeMinutes);

        return AiProviderAccount::query()
            ->where('provider', $provider)
            ->where('is_enabled', true)
            ->where(function ($query) use ($staleThreshold): void {
                $query
                    ->whereNull('last_status_checked_at')
                    ->orWhere('last_status_checked_at', '<=', $staleThreshold);
            })
            ->exists();
    }

    private function refreshStateCacheKey(string $provider): string
    {
        return 'ai-provider-stats-refreshing:'.$provider;
    }
}
