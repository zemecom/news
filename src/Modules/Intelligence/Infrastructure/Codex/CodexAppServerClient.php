<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\DTO\AiProviderProfile;

final readonly class CodexAppServerClient implements CodexAppServerClientContract
{
    public function __construct(
        private CodexProcessRunnerContract $runner,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function startLogin(AiProviderProfile $account): array
    {
        return $this->request($account, 'account/login/start', ['type' => 'chatgpt']);
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelLogin(AiProviderProfile $account): array
    {
        return $this->request($account, 'account/login/cancel', [
            'loginId' => (string) $account->loginId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function readAccount(AiProviderProfile $account): array
    {
        return $this->request($account, 'account/read', ['refreshToken' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    public function readRateLimits(AiProviderProfile $account): array
    {
        return $this->request($account, 'account/rateLimits/read');
    }

    public function logout(AiProviderProfile $account): void
    {
        $this->request($account, 'account/logout');
    }

    /**
     * @param  array<string, mixed>|null  $params
     * @return array<string, mixed>
     */
    private function request(AiProviderProfile $account, string $method, ?array $params = null): array
    {
        $requestId = 2;
        $response = $this->runner->runJsonSession(
            command: [
                (string) config('intelligence.chatgpt_codex.binary', 'codex'),
                'app-server',
                '--listen',
                'stdio://',
            ],
            messages: [
                [
                    'id' => 1,
                    'method' => 'initialize',
                    'params' => [
                        'protocolVersion' => (int) config('intelligence.chatgpt_codex.protocol_version', 2),
                        'clientInfo' => [
                            'name' => (string) config('intelligence.chatgpt_codex.client_name', 'SmartNews'),
                            'version' => (string) config('intelligence.chatgpt_codex.client_version', '1.0.0'),
                        ],
                    ],
                ],
                [
                    'id' => $requestId,
                    'method' => $method,
                    'params' => $params,
                ],
            ],
            env: [
                'CODEX_HOME' => $this->resolveCodexHome($account),
            ],
            timeoutSeconds: (int) config('intelligence.chatgpt_codex.app_server_timeout_seconds', 15),
        );

        if ($response['exit_code'] !== 0 && $method !== 'account/rateLimits/read') {
            $this->throwForError(
                method: $method,
                output: $response['output'],
                errorOutput: $response['error_output'],
            );
        }

        foreach ($response['decoded'] as $payload) {
            if (($payload['id'] ?? null) !== $requestId) {
                continue;
            }

            if (isset($payload['error']) && is_array($payload['error'])) {
                $this->throwForPayloadError($method, $payload['error']);
            }

            $result = $payload['result'] ?? null;

            return is_array($result) ? $result : [];
        }

        if ($method === 'account/rateLimits/read') {
            return [];
        }

        throw new CodexException(sprintf('No Codex app-server response for method `%s`.', $method));
    }

    private function resolveCodexHome(AiProviderProfile $account): string
    {
        $base = rtrim((string) config('intelligence.chatgpt_codex.home_base', '/home/www-data/.codex/providers'), '/');
        $path = $base.'/'.$account->codexHomeSubpath;

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    private function throwForError(string $method, string $output, string $errorOutput): never
    {
        $message = trim($errorOutput !== '' ? $errorOutput : $output);

        if (str_contains($message, 'usageLimitExceeded')) {
            throw new CodexUsageLimitExceededException(sprintf('Codex app-server rate limit error on `%s`: %s', $method, $message));
        }

        if (str_contains(strtolower($message), 'unauthorized')) {
            throw new CodexUnauthorizedException(sprintf('Codex app-server unauthorized on `%s`: %s', $method, $message));
        }

        throw new CodexException(sprintf('Codex app-server request `%s` failed: %s', $method, $message));
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function throwForPayloadError(string $method, array $error): never
    {
        $message = (string) ($error['message'] ?? 'Unknown Codex app-server error.');

        if (str_contains($message, 'usageLimitExceeded')) {
            throw new CodexUsageLimitExceededException(sprintf('Codex app-server rate limit error on `%s`: %s', $method, $message));
        }

        if (str_contains(strtolower($message), 'unauthorized')) {
            throw new CodexUnauthorizedException(sprintf('Codex app-server unauthorized on `%s`: %s', $method, $message));
        }

        throw new CodexException(sprintf('Codex app-server error on `%s`: %s', $method, $message));
    }
}
