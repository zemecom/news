<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Carbon\CarbonImmutable;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AiProviderAccountRateLimitTest extends TestCase
{
    public function test_it_reads_primary_and_weekly_rate_limit_windows_from_snapshot(): void
    {
        $account = new AiProviderAccount([
            'rate_limit_snapshot' => [
                'rateLimits' => [
                    'primary' => [
                        'usedPercent' => 36,
                        'resetsAt' => 1_773_875_567,
                        'windowDurationMins' => 300,
                    ],
                    'secondary' => [
                        'usedPercent' => 10,
                        'resetsAt' => 1_774_467_048,
                        'windowDurationMins' => 10_080,
                    ],
                ],
            ],
        ]);

        $this->assertSame(36, $account->rateLimitUsedPercent());
        $this->assertEquals(
            CarbonImmutable::createFromTimestampUTC(1_773_875_567),
            $account->rateLimitResetAt(),
        );
        $this->assertSame(10, $account->weeklyRateLimitUsedPercent());
        $this->assertEquals(
            CarbonImmutable::createFromTimestampUTC(1_774_467_048),
            $account->weeklyRateLimitResetAt(),
        );
    }

    public function test_it_falls_back_to_weekly_window_by_duration_when_secondary_key_is_missing(): void
    {
        $account = new AiProviderAccount([
            'rate_limit_snapshot' => [
                'rateLimits' => [
                    'primary' => [
                        'usedPercent' => 36,
                        'resetsAt' => 1_773_875_567,
                        'windowDurationMins' => 300,
                    ],
                    'custom_weekly' => [
                        'usedPercent' => 12,
                        'resetsAt' => 1_774_467_048,
                        'windowDurationMins' => 10_080,
                    ],
                ],
            ],
        ]);

        $this->assertSame(12, $account->weeklyRateLimitUsedPercent());
        $this->assertEquals(
            CarbonImmutable::createFromTimestampUTC(1_774_467_048),
            $account->weeklyRateLimitResetAt(),
        );
    }
}
