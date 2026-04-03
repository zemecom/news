<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Infrastructure\Codex\CodexAccountStatusSynchronizer;
use Modules\Intelligence\Infrastructure\Codex\CodexAppServerClientContract;
use Modules\Intelligence\Infrastructure\Codex\CodexAuthProcessManager;
use Modules\Intelligence\Infrastructure\Codex\CodexUnauthorizedException;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class CodexAccountStatusSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_keeps_pending_status_while_device_auth_process_is_still_running(): void
    {
        $account = AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_PENDING,
            'auth_mode' => 'chatgpt_device',
            'login_id' => '46F0-RWVKM',
            'auth_url' => 'https://auth.openai.com/codex/device',
            'meta' => [
                'pending_login_process' => [
                    'pid' => 321,
                ],
            ],
        ]);

        $client = $this->createMock(CodexAppServerClientContract::class);
        $client->expects($this->once())
            ->method('readAccount')
            ->willThrowException(new CodexUnauthorizedException('unauthorized'));
        $client->expects($this->never())->method('readRateLimits');
        $authProcesses = $this->createMock(CodexAuthProcessManager::class);
        $authProcesses->expects($this->once())
            ->method('isRunning')
            ->with(321)
            ->willReturn(true);
        $authProcesses->expects($this->never())
            ->method('terminate');

        $synchronizer = new CodexAccountStatusSynchronizer($client, $authProcesses);
        $synchronizer->sync($account);

        $account->refresh();

        $this->assertSame(AiProviderAccount::STATUS_PENDING, $account->auth_status);
        $this->assertSame('46F0-RWVKM', $account->login_id);
        $this->assertSame('https://auth.openai.com/codex/device', $account->auth_url);
    }
}
