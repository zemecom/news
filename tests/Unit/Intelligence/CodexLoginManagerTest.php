<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Infrastructure\Codex\CodexAccountStatusSynchronizer;
use Modules\Intelligence\Infrastructure\Codex\CodexAppServerClient;
use Modules\Intelligence\Infrastructure\Codex\CodexAuthProcessManager;
use Modules\Intelligence\Infrastructure\Codex\CodexLoginManager;
use Modules\Intelligence\Infrastructure\Codex\CodexProcessRunnerContract;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class CodexLoginManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_login_uses_device_auth_and_persists_code_url_and_pid(): void
    {
        $account = $this->account([
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $client = new CodexAppServerClient($runner);
        $authProcesses = $this->createMock(CodexAuthProcessManager::class);
        $authProcesses->expects($this->once())
            ->method('startDeviceAuth')
            ->willReturnCallback(function (string $binary, string $codexHome, string $outputPath): int {
                $this->assertSame('codex', $binary);
                $this->assertStringContainsString('chatgpt-default', $codexHome);

                file_put_contents($outputPath, <<<'TEXT'
Welcome to Codex
1. Open this link in your browser and sign in to your account
https://auth.openai.com/codex/device

2. Enter this one-time code
46F0-RWVKM
TEXT);

                return 321;
            });

        $manager = new CodexLoginManager(
            $client,
            new CodexAccountStatusSynchronizer($client, $authProcesses),
            $authProcesses,
        );

        $manager->startLogin($account);

        $account->refresh();

        $this->assertSame(AiProviderAccount::STATUS_PENDING, $account->auth_status);
        $this->assertSame('chatgpt_device', $account->auth_mode);
        $this->assertSame('https://auth.openai.com/codex/device', $account->auth_url);
        $this->assertSame('46F0-RWVKM', $account->login_id);
        $this->assertIsArray($account->meta);
        $this->assertIsArray($account->meta['pending_login_process'] ?? null);
        $this->assertSame(321, $account->meta['pending_login_process']['pid']);
    }

    public function test_cancel_login_terminates_pending_device_auth_process_and_clears_status(): void
    {
        $outputPath = tempnam(sys_get_temp_dir(), 'codex-device-auth-cancel-');
        file_put_contents((string) $outputPath, 'device auth pending');

        $account = $this->account([
            'auth_status' => AiProviderAccount::STATUS_PENDING,
            'auth_mode' => 'chatgpt_device',
            'login_id' => '46F0-RWVKM',
            'auth_url' => 'https://auth.openai.com/codex/device',
            'meta' => [
                'pending_login_process' => [
                    'pid' => 321,
                    'output_path' => $outputPath,
                ],
            ],
        ]);

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $client = new CodexAppServerClient($runner);
        $authProcesses = $this->createMock(CodexAuthProcessManager::class);
        $authProcesses->expects($this->once())
            ->method('terminate')
            ->with(321);

        $manager = new CodexLoginManager(
            $client,
            new CodexAccountStatusSynchronizer($client, $authProcesses),
            $authProcesses,
        );

        $manager->cancelLogin($account);

        $account->refresh();

        $this->assertSame(AiProviderAccount::STATUS_NOT_AUTHENTICATED, $account->auth_status);
        $this->assertNull($account->auth_mode);
        $this->assertNull($account->auth_url);
        $this->assertNull($account->login_id);
        $this->assertNull($account->meta);
        $this->assertFileDoesNotExist((string) $outputPath);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function account(array $overrides = []): AiProviderAccount
    {
        /** @var AiProviderAccount $account */
        $account = AiProviderAccount::query()->create(array_merge([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ], $overrides));

        return $account;
    }
}
