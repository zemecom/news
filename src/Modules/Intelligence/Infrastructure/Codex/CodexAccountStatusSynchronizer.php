<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Domain\Exceptions\AiProviderUnauthorizedException;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;

final readonly class CodexAccountStatusSynchronizer implements AiProviderStatusManager
{
    public function __construct(
        private CodexAppServerClient $client,
        private CodexAuthProcessManager $authProcesses,
    ) {}

    public function sync(AiProviderProfile|AiProviderAccount $account): void
    {
        $model = $this->resolveModel($account);

        try {
            $accountPayload = $this->client->readAccount($model->toProfile());
        } catch (AiProviderUnauthorizedException) {
            if ($model->isPending() && $this->hasActivePendingLoginProcess($model)) {
                $model->forceFill([
                    'last_status_checked_at' => now(),
                    'last_error_at' => null,
                    'last_error_message' => null,
                ])->save();

                return;
            }

            $this->clearPendingLoginProcess($model);
            $this->markNotAuthenticated($model->toProfile());

            return;
        }

        $accountData = $accountPayload['account'] ?? null;
        $updates = [
            'last_status_checked_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ];

        if (is_array($accountData) && ($accountData['type'] ?? null) === 'chatgpt' && ($accountData['email'] ?? null) !== null) {
            $this->clearPendingLoginProcess($model);
            $updates['auth_status'] = AiProviderAccount::STATUS_AUTHENTICATED;
            $updates['auth_mode'] = 'chatgpt';
            $updates['account_email'] = (string) $accountData['email'];
            $updates['plan_type'] = isset($accountData['planType']) ? (string) $accountData['planType'] : null;
            $updates['auth_url'] = null;
            $updates['login_id'] = null;
            $updates['last_authenticated_at'] = now();
            $updates['meta'] = $this->withoutPendingLoginProcess($model);
        } elseif ($model->isPending()) {
            if ($this->hasActivePendingLoginProcess($model)) {
                $updates['auth_status'] = AiProviderAccount::STATUS_PENDING;
            } else {
                $this->clearPendingLoginProcess($model);
                $updates['auth_status'] = AiProviderAccount::STATUS_NOT_AUTHENTICATED;
                $updates['auth_mode'] = null;
                $updates['auth_url'] = null;
                $updates['login_id'] = null;
                $updates['meta'] = $this->withoutPendingLoginProcess($model);
            }
        } else {
            $this->clearPendingLoginProcess($model);
            $updates['auth_status'] = AiProviderAccount::STATUS_NOT_AUTHENTICATED;
            $updates['account_email'] = null;
            $updates['plan_type'] = null;
            $updates['meta'] = $this->withoutPendingLoginProcess($model);
        }

        try {
            $rateLimits = $this->client->readRateLimits($model->toProfile());
            if ($rateLimits !== []) {
                $updates['rate_limit_snapshot'] = $rateLimits;
            }
        } catch (CodexException $e) {
            $meta = is_array($model->meta) ? $model->meta : [];
            $updates['meta'] = array_merge($meta, [
                'rate_limits_read_error' => $e->getMessage(),
            ]);
        }

        $model->forceFill($updates)->save();
    }

    public function markPending(AiProviderProfile|AiProviderAccount $account, string $loginId, string $authUrl): void
    {
        $model = $this->resolveModel($account);

        $model->forceFill([
            'auth_status' => AiProviderAccount::STATUS_PENDING,
            'auth_mode' => 'chatgpt',
            'login_id' => $loginId,
            'auth_url' => $authUrl,
            'last_status_checked_at' => now(),
            'last_error_at' => null,
            'last_error_message' => null,
        ])->save();
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public function markUsageLimited(AiProviderProfile|AiProviderAccount $account, ?array $snapshot = null, ?string $message = null): void
    {
        $model = $this->resolveModel($account);

        $model->forceFill([
            'auth_status' => AiProviderAccount::STATUS_RATE_LIMITED,
            'rate_limit_snapshot' => $snapshot ?? $model->rate_limit_snapshot,
            'last_status_checked_at' => now(),
            'last_error_at' => now(),
            'last_error_message' => $message ?? 'usageLimitExceeded',
        ])->save();
    }

    public function markError(AiProviderProfile|AiProviderAccount $account, string $message): void
    {
        $model = $this->resolveModel($account);

        $model->forceFill([
            'auth_status' => AiProviderAccount::STATUS_ERROR,
            'last_status_checked_at' => now(),
            'last_error_at' => now(),
            'last_error_message' => $message,
        ])->save();
    }

    public function markNotAuthenticated(AiProviderProfile|AiProviderAccount $account): void
    {
        $model = $this->resolveModel($account);
        $this->clearPendingLoginProcess($model);

        $model->forceFill([
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
            'auth_mode' => null,
            'auth_url' => null,
            'login_id' => null,
            'account_email' => null,
            'plan_type' => null,
            'last_status_checked_at' => now(),
            'meta' => $this->withoutPendingLoginProcess($model),
        ])->save();
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
            throw new CodexException(sprintf('AI provider account `%s` was not found for status sync.', $account->slug));
        }

        return $model;
    }

    private function hasActivePendingLoginProcess(AiProviderAccount $account): bool
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        $process = is_array($meta['pending_login_process'] ?? null) ? $meta['pending_login_process'] : [];
        $pid = isset($process['pid']) ? (int) $process['pid'] : 0;

        return $pid > 0 && $this->authProcesses->isRunning($pid);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function withoutPendingLoginProcess(AiProviderAccount $account): ?array
    {
        $meta = is_array($account->meta) ? $account->meta : [];
        unset($meta['pending_login_process']);

        return $meta !== [] ? $meta : null;
    }

    private function clearPendingLoginProcess(AiProviderAccount $account): void
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
}
