<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\EloquentSourceRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Shared\Application\Services\SourceRuntimeHealthPolicy;
use Tests\TestCase;

final class EloquentSourceRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_update_success_resets_backoff_state(): void
    {
        $source = Source::query()->create([
            'name' => 'Test Source',
            'url' => 'https://example.com/feed.xml',
            'type' => 'rss',
            'language_default' => 'en',
            'cron_expression' => '* * * * *',
            'is_active' => true,
            'error_streak' => 3,
            'retry_backoff_state' => [
                'next_retry_at' => '2026-03-17T10:30:00+00:00',
            ],
            'last_error_at' => now()->subHour(),
        ]);

        $repo = new EloquentSourceRepository(new SourceRuntimeHealthPolicy);
        $repo->updateSuccess((int) $source->getKey());

        $source->refresh();

        $this->assertNotNull($source->getAttribute('last_success_at'));
        $this->assertSame(0, (int) $source->getAttribute('error_streak'));
        $this->assertNull($source->getAttribute('retry_backoff_state'));
    }

    public function test_update_failure_increments_error_streak_and_sets_retry_state(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-03-17T10:00:00+03:00'));
        config()->set('crawler.runtime.health.enabled', true);
        config()->set('crawler.runtime.health.backoff_after_streak', 2);
        config()->set('crawler.runtime.health.base_backoff_minutes', 5);
        config()->set('crawler.runtime.health.max_backoff_minutes', 60);

        $source = Source::query()->create([
            'name' => 'Test Source',
            'url' => 'https://example.com/feed.xml',
            'type' => 'rss',
            'language_default' => 'en',
            'cron_expression' => '* * * * *',
            'is_active' => true,
            'error_streak' => 2,
        ]);

        $repo = new EloquentSourceRepository(new SourceRuntimeHealthPolicy);
        $repo->updateFailure((int) $source->getKey());

        $source->refresh();

        $this->assertSame(3, (int) $source->getAttribute('error_streak'));
        $this->assertNotNull($source->getAttribute('last_error_at'));
        $this->assertSame([
            'error_streak' => 3,
            'failed_at' => '2026-03-17T10:00:00+03:00',
            'backoff_minutes' => 10,
            'next_retry_at' => '2026-03-17T10:10:00+03:00',
        ], $source->getAttribute('retry_backoff_state'));
    }
}
