<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Crawler\Application\Jobs\FetchSourceJob;
use Tests\TestCase;

final class NewsCrawlCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_skips_sources_in_backoff_window(): void
    {
        Queue::fake();

        $blockedSource = $this->createSource(
            name: 'Blocked Source',
            url: 'https://blocked.example.com/feed.xml',
            retryBackoffState: ['next_retry_at' => now()->addMinutes(30)->toIso8601String()],
            errorStreak: 4
        );
        $healthySource = $this->createSource(
            name: 'Healthy Source',
            url: 'https://healthy.example.com/feed.xml'
        );

        $this->assertSame(0, Artisan::call('news:crawl'));

        Queue::assertPushed(
            FetchSourceJob::class,
            fn (FetchSourceJob $job): bool => (int) $job->source['id'] === (int) $healthySource->getKey()
        );
        Queue::assertNotPushed(
            FetchSourceJob::class,
            fn (FetchSourceJob $job): bool => (int) $job->source['id'] === (int) $blockedSource->getKey()
        );
        Queue::assertPushedTimes(FetchSourceJob::class, 1);
    }

    public function test_it_can_ignore_backoff_when_option_is_enabled(): void
    {
        Queue::fake();

        $blockedSource = $this->createSource(
            name: 'Blocked Source',
            url: 'https://blocked.example.com/feed.xml',
            retryBackoffState: ['next_retry_at' => now()->addMinutes(30)->toIso8601String()],
            errorStreak: 4
        );

        $this->assertSame(0, Artisan::call('news:crawl --ignore-backoff'));

        Queue::assertPushed(
            FetchSourceJob::class,
            fn (FetchSourceJob $job): bool => (int) $job->source['id'] === (int) $blockedSource->getKey()
        );
        Queue::assertPushedTimes(FetchSourceJob::class, 1);
    }

    /**
     * @param  array<string, mixed>|null  $retryBackoffState
     */
    private function createSource(
        string $name,
        string $url,
        ?array $retryBackoffState = null,
        int $errorStreak = 0,
    ): Source {
        /** @var Source $source */
        $source = Source::query()->create([
            'name' => $name,
            'url' => $url,
            'type' => 'rss',
            'language_default' => 'en',
            'cron_expression' => '* * * * *',
            'is_active' => true,
            'retry_backoff_state' => $retryBackoffState,
            'error_streak' => $errorStreak,
            'last_error_at' => $retryBackoffState !== null ? now()->subMinute() : null,
        ]);

        return $source;
    }
}
