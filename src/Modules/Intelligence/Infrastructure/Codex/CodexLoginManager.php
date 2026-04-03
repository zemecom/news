<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\Contracts\AiProviderAuthManager;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final readonly class CodexLoginManager implements AiProviderAuthManager
{
    public function __construct(
        private CodexAppServerClientContract $client,
        private AiProviderStatusManager $statusSynchronizer,
        private CodexAuthProcessManager $authProcesses,
    ) {}

    public function startLogin(AiProviderProfile|AiProviderAccount $account): void
    {
        $account = $this->resolveModel($account);

        $this->stopPendingLoginProcess($account);

        $outputPath = $this->allocateOutputPath($account);
        $pid = $this->authProcesses->startDeviceAuth(
            binary: (string) config('intelligence.chatgpt_codex.binary', 'codex'),
            codexHome: $this->resolveCodexHome($account),
            outputPath: $outputPath,
        );

        [$authUrl, $deviceCode] = $this->waitForDeviceAuthPrompt($pid, $outputPath);

        $meta = is_array($account->meta) ? $account->meta : [];
        $meta['pending_login_process'] = [
            'pid' => $pid,
            'output_path' => $outputPath,
            'started_at' => now()->toIso8601String(),
        ];

        $account->forceFill([
            'auth_status' => AiProviderAccount::STATUS_PENDING,
            'auth_mode' => 'chatgpt_device',
            'login_id' => $deviceCode,
            'auth_url' => $authUrl,
            'last_status_checked_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
            'meta' => $meta,
        ])->save();
    }

    public function cancelLogin(AiProviderProfile|AiProviderAccount $account): void
    {
        $account = $this->resolveModel($account);

        $this->statusSynchronizer->markNotAuthenticated($account->toProfile());
    }

    public function logout(AiProviderProfile|AiProviderAccount $account): void
    {
        $account = $this->resolveModel($account);

        $this->client->logout($account->toProfile());

        $this->statusSynchronizer->markNotAuthenticated($account->toProfile());
    }

    private function resolveCodexHome(AiProviderAccount $account): string
    {
        $base = rtrim((string) config('intelligence.chatgpt_codex.home_base', '/home/www-data/.codex/providers'), '/');
        $path = $base.'/'.$account->codex_home_subpath;

        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }

    private function allocateOutputPath(AiProviderAccount $account): string
    {
        $path = tempnam(sys_get_temp_dir(), sprintf('codex-device-auth-%s-', $account->slug));
        if ($path === false) {
            throw new CodexException('Failed to allocate output file for Codex device auth.');
        }

        return $path;
    }

    /**
     * @return array{0:string,1:string}
     */
    private function waitForDeviceAuthPrompt(int $pid, string $outputPath): array
    {
        $deadline = microtime(true) + 10;

        while (microtime(true) < $deadline) {
            $output = $this->normalizedOutput($outputPath);
            $authUrl = $this->extractAuthUrl($output);
            $deviceCode = $this->extractDeviceCode($output);

            if ($authUrl !== null && $deviceCode !== null) {
                return [$authUrl, $deviceCode];
            }

            if (! $this->authProcesses->isRunning($pid)) {
                throw new CodexException('Codex device auth process exited before providing auth link/code. Output: '.$output);
            }

            usleep(200_000);
        }

        throw new CodexException('Timed out while waiting for Codex device auth link/code.');
    }

    private function extractAuthUrl(string $output): ?string
    {
        if (preg_match('/https:\/\/auth\.openai\.com\/\S+/', $output, $matches) !== 1) {
            return null;
        }

        return $matches[0];
    }

    private function extractDeviceCode(string $output): ?string
    {
        if (preg_match('/\b([A-Z0-9]{4,}(?:-[A-Z0-9]{4,})+)\b/', $output, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    private function normalizedOutput(string $outputPath): string
    {
        $contents = is_file($outputPath) ? (string) file_get_contents($outputPath) : '';

        return preg_replace('/\e\[[0-9;]*m/', '', $contents) ?? $contents;
    }

    private function stopPendingLoginProcess(AiProviderAccount $account): void
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $process = is_array($meta['pending_login_process'] ?? null) ? $meta['pending_login_process'] : [];
        $pid = isset($process['pid']) ? (int) $process['pid'] : 0;
        $outputPath = isset($process['output_path']) ? (string) $process['output_path'] : null;

        if ($pid > 0) {
            $this->authProcesses->terminate($pid);
        }

        if ($outputPath !== null && is_file($outputPath)) {
            @unlink($outputPath);
        }
    }

    private function resolveModel(AiProviderProfile|AiProviderAccount $account): AiProviderAccount
    {
        if ($account instanceof AiProviderAccount) {
            return $account;
        }

        $query = AiProviderAccount::query();
        $model = $account->id !== null ? $query->find($account->id) : null;

        if (! $model instanceof AiProviderAccount) {
            /** @var AiProviderAccount|null $model */
            $model = AiProviderAccount::query()
                ->where('slug', $account->slug)
                ->first();
        }

        if (! $model instanceof AiProviderAccount) {
            throw new CodexException(sprintf('AI provider account `%s` was not found for auth flow.', $account->slug));
        }

        return $model;
    }
}
