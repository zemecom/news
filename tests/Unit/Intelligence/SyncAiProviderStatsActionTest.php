<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Modules\Intelligence\Application\Services\SyncAiProviderStatsAction;
use Modules\Intelligence\Domain\Contracts\AiProviderAccountRepository;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use RuntimeException;
use Tests\TestCase;

final class SyncAiProviderStatsActionTest extends TestCase
{
    public function test_it_syncs_all_enabled_provider_accounts_and_continues_after_failures(): void
    {
        $failedProfile = $this->makeProfile('chatgpt-default');
        $okProfile = $this->makeProfile('chatgpt-backup');

        $repository = $this->createMock(AiProviderAccountRepository::class);
        $repository
            ->expects($this->once())
            ->method('findEnabledByProvider')
            ->with(AiProviderProfile::PROVIDER_CHATGPT_CODEX)
            ->willReturn([$failedProfile, $okProfile]);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager
            ->expects($this->exactly(2))
            ->method('sync')
            ->willReturnCallback(function (AiProviderProfile $profile) use ($failedProfile): void {
                if ($profile->slug === $failedProfile->slug) {
                    throw new RuntimeException('temporary app-server error');
                }
            });
        $statusManager
            ->expects($this->once())
            ->method('markError')
            ->with($failedProfile, 'Stats sync failed: temporary app-server error');

        $action = new SyncAiProviderStatsAction($repository, $statusManager);

        $this->assertSame(
            [
                'checked' => 2,
                'updated' => 1,
                'failed' => 1,
            ],
            $action->run(),
        );
    }

    private function makeProfile(string $slug): AiProviderProfile
    {
        return new AiProviderProfile(
            id: null,
            slug: $slug,
            provider: AiProviderProfile::PROVIDER_CHATGPT_CODEX,
            displayName: 'ChatGPT Codex',
            enabled: true,
            codexHomeSubpath: $slug,
            defaultModel: 'gpt-5.4-mini',
            maxParallelJobs: 1,
            authStatus: AiProviderProfile::STATUS_AUTHENTICATED,
        );
    }
}
