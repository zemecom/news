<?php

declare(strict_types=1);

namespace Modules\Shared\Application\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Throwable;

final class SourceRuntimeHealthPolicy
{
    /**
     * @param  array<string, mixed>|null  $retryBackoffState
     */
    public function resolveNextRetryAt(?array $retryBackoffState, int $errorStreak, mixed $lastErrorAt): ?CarbonImmutable
    {
        $nextRetryAtFromState = $this->parseDateTime($retryBackoffState['next_retry_at'] ?? null);
        if ($nextRetryAtFromState instanceof \Carbon\CarbonImmutable) {
            return $nextRetryAtFromState;
        }

        $failedAt = $this->parseDateTime($lastErrorAt);
        if (! $failedAt instanceof \Carbon\CarbonImmutable) {
            return null;
        }

        return $this->calculateNextRetryAt($errorStreak, $failedAt);
    }

    public function calculateBackoffMinutes(int $errorStreak): int
    {
        if (! (bool) config('crawler.runtime.health.enabled', true)) {
            return 0;
        }

        if ($errorStreak <= 0) {
            return 0;
        }

        $backoffAfterStreak = max(1, (int) config('crawler.runtime.health.backoff_after_streak', 2));
        if ($errorStreak < $backoffAfterStreak) {
            return 0;
        }

        $baseMinutes = max(1, (int) config('crawler.runtime.health.base_backoff_minutes', 5));
        $maxMinutes = max($baseMinutes, (int) config('crawler.runtime.health.max_backoff_minutes', 720));
        $exponent = min($errorStreak - $backoffAfterStreak, 10);

        $calculated = $baseMinutes * (2 ** $exponent);

        return min($maxMinutes, $calculated);
    }

    public function calculateNextRetryAt(int $errorStreak, CarbonInterface $failedAt): ?CarbonImmutable
    {
        $backoffMinutes = $this->calculateBackoffMinutes($errorStreak);
        if ($backoffMinutes <= 0) {
            return null;
        }

        return CarbonImmutable::instance($failedAt)->addMinutes($backoffMinutes);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildFailureBackoffState(int $errorStreak, CarbonInterface $failedAt): ?array
    {
        $nextRetryAt = $this->calculateNextRetryAt($errorStreak, $failedAt);
        if (! $nextRetryAt instanceof \Carbon\CarbonImmutable) {
            return null;
        }

        $failedAtImmutable = CarbonImmutable::instance($failedAt);

        return [
            'error_streak' => $errorStreak,
            'failed_at' => $failedAtImmutable->toIso8601String(),
            'backoff_minutes' => $this->calculateBackoffMinutes($errorStreak),
            'next_retry_at' => $nextRetryAt->toIso8601String(),
        ];
    }

    public function isInBackoffWindow(?CarbonImmutable $nextRetryAt, ?CarbonInterface $now = null): bool
    {
        if (! $nextRetryAt instanceof \Carbon\CarbonImmutable) {
            return false;
        }

        $referenceNow = $now instanceof \Carbon\CarbonInterface
            ? CarbonImmutable::instance($now)
            : CarbonImmutable::now();

        return $referenceNow->lt($nextRetryAt);
    }

    private function parseDateTime(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
