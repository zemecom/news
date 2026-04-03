<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Support\AiProviderStatsAutoRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AiProviderStatsAutoRefresherTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_stale_enabled_provider_stats(): void
    {
        AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'default_reasoning_effort' => 'high',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_AUTHENTICATED,
            'last_status_checked_at' => now()->subMinutes(6),
        ]);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->once())
            ->method('sync')
            ->with($this->callback(static function (AiProviderProfile $profile): bool {
                return $profile->provider === AiProviderAccount::PROVIDER_CHATGPT_CODEX
                    && $profile->slug === 'chatgpt-default';
            }));
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->never())->method('markNotAuthenticated');
        $statusManager->expects($this->never())->method('markError');

        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $refresher = new AiProviderStatsAutoRefresher($this->app->make(SyncAiProviderStatsAction::class));

        self::assertTrue($refresher->refreshIfStale());
    }

    public function test_it_skips_refresh_when_provider_stats_are_fresh(): void
    {
        AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'default_reasoning_effort' => 'high',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_AUTHENTICATED,
            'last_status_checked_at' => now()->subMinutes(4),
        ]);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->never())->method('sync');
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->never())->method('markNotAuthenticated');
        $statusManager->expects($this->never())->method('markError');

        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $refresher = new AiProviderStatsAutoRefresher($this->app->make(SyncAiProviderStatsAction::class));

        self::assertFalse($refresher->refreshIfStale());
    }
}
