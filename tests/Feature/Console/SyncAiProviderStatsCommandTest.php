<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use Modules\Intelligence\Domain\Contracts\AiProviderAccountRepository;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Tests\TestCase;

final class SyncAiProviderStatsCommandTest extends TestCase
{
    public function test_it_runs_ai_provider_stats_sync_command(): void
    {
        $profile = new AiProviderProfile(
            id: 1,
            slug: 'chatgpt-default',
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            displayName: 'ChatGPT Codex',
            enabled: true,
            codexHomeSubpath: 'chatgpt-default',
            defaultModel: 'gpt-5.4-mini',
            maxParallelJobs: 1,
            authStatus: AiProviderProfile::STATUS_AUTHENTICATED,
        );

        $repository = $this->createMock(AiProviderAccountRepository::class);
        $repository
            ->expects($this->once())
            ->method('findEnabledByProvider')
            ->with('chatgpt_codex')
            ->willReturn([$profile]);
        $repository
            ->expects($this->never())
            ->method('findFirstEnabledByProvider');
        $this->app->instance(AiProviderAccountRepository::class, $repository);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager
            ->expects($this->once())
            ->method('sync')
            ->with($profile);
        $statusManager
            ->expects($this->never())
            ->method('markError');
        $statusManager
            ->expects($this->never())
            ->method('markNotAuthenticated');
        $statusManager
            ->expects($this->never())
            ->method('markUsageLimited');
        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $exitCode = Artisan::call('ai-providers:sync-stats');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString(
            'Synced stats for 1 provider(s): updated=1, failed=0.',
            Artisan::output(),
        );
    }

    public function test_schedule_list_contains_ai_provider_stats_sync(): void
    {
        Artisan::call('schedule:list');

        $output = Artisan::output();

        $this->assertStringContainsString('ai-providers:sync-stats', $output);
        $this->assertStringContainsString('*/5 * * * *', $output);
    }
}
