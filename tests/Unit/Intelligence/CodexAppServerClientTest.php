<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Codex\CodexAppServerClient;
use Modules\Intelligence\Infrastructure\Codex\CodexProcessRunnerContract;
use Tests\TestCase;

final class CodexAppServerClientTest extends TestCase
{
    public function test_read_account_returns_result_payload(): void
    {
        config()->set('intelligence.chatgpt_codex.home_base', sys_get_temp_dir().'/codex-app-server-tests');

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $runner->expects($this->once())
            ->method('runJsonSession')
            ->with(
                ['codex', 'app-server', '--listen', 'stdio://'],
                $this->callback(static function (array $messages): bool {
                    return $messages[1]['method'] === 'account/read'
                        && $messages[1]['params'] === ['refreshToken' => false];
                }),
                null,
                $this->callback(static fn (array $env): bool => str_contains($env['CODEX_HOME'] ?? '', 'chatgpt-default')),
                15,
            )
            ->willReturn([
                'exit_code' => 0,
                'output' => '',
                'error_output' => '',
                'decoded' => [
                    ['id' => 2, 'result' => [
                        'account' => [
                            'type' => 'chatgpt',
                            'email' => 'admin@example.com',
                            'planType' => 'plus',
                        ],
                        'requiresOpenaiAuth' => false,
                    ]],
                ],
            ]);

        $client = new CodexAppServerClient($runner);

        $result = $client->readAccount($this->account());

        $this->assertSame('chatgpt', $result['account']['type']);
        $this->assertSame('admin@example.com', $result['account']['email']);
        $this->assertFalse($result['requiresOpenaiAuth']);
    }

    public function test_read_rate_limits_returns_empty_array_when_process_fails(): void
    {
        config()->set('intelligence.chatgpt_codex.home_base', sys_get_temp_dir().'/codex-app-server-tests');

        $runner = $this->createMock(CodexProcessRunnerContract::class);
        $runner->expects($this->once())
            ->method('runJsonSession')
            ->willReturn([
                'exit_code' => 1,
                'output' => '',
                'error_output' => 'panic',
                'decoded' => [],
            ]);

        $client = new CodexAppServerClient($runner);

        $result = $client->readRateLimits($this->account());

        $this->assertSame([], $result);
    }

    private function account(): AiProviderProfile
    {
        return new AiProviderProfile(
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
    }
}
