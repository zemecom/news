<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Carbon\CarbonImmutable;
use Modules\Shared\Application\Services\SourceRuntimeHealthPolicy;
use Tests\TestCase;

final class SourceRuntimeHealthPolicyTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_resolve_next_retry_at_prefers_state_value(): void
    {
        $policy = new SourceRuntimeHealthPolicy;

        $nextRetryAt = $policy->resolveNextRetryAt([
            'next_retry_at' => '2026-03-17T12:30:00+00:00',
        ], 4, '2026-03-17T10:00:00+00:00');

        $this->assertInstanceOf(CarbonImmutable::class, $nextRetryAt);
        $this->assertSame('2026-03-17T12:30:00+00:00', $nextRetryAt->toIso8601String());
    }

    public function test_calculate_backoff_minutes_respects_runtime_configuration(): void
    {
        config()->set('crawler.runtime.health.enabled', true);
        config()->set('crawler.runtime.health.backoff_after_streak', 2);
        config()->set('crawler.runtime.health.base_backoff_minutes', 5);
        config()->set('crawler.runtime.health.max_backoff_minutes', 60);

        $policy = new SourceRuntimeHealthPolicy;

        $this->assertSame(0, $policy->calculateBackoffMinutes(1));
        $this->assertSame(5, $policy->calculateBackoffMinutes(2));
        $this->assertSame(10, $policy->calculateBackoffMinutes(3));
        $this->assertSame(40, $policy->calculateBackoffMinutes(5));
    }

    public function test_build_failure_backoff_state_serializes_next_retry_information(): void
    {
        config()->set('crawler.runtime.health.enabled', true);
        config()->set('crawler.runtime.health.backoff_after_streak', 2);
        config()->set('crawler.runtime.health.base_backoff_minutes', 5);
        config()->set('crawler.runtime.health.max_backoff_minutes', 60);

        $policy = new SourceRuntimeHealthPolicy;
        $failedAt = CarbonImmutable::parse('2026-03-17T10:00:00+00:00');

        $state = $policy->buildFailureBackoffState(3, $failedAt);

        $this->assertSame([
            'error_streak' => 3,
            'failed_at' => '2026-03-17T10:00:00+00:00',
            'backoff_minutes' => 10,
            'next_retry_at' => '2026-03-17T10:10:00+00:00',
        ], $state);
    }
}
