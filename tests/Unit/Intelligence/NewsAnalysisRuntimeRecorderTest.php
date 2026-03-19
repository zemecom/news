<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Intelligence\Application\Services\NewsAnalysisRuntimeRecorder;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class NewsAnalysisRuntimeRecorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_attempt_lifecycle_and_preserves_fallback_on_completion(): void
    {
        $item = $this->createNewsItem();
        /** @var NewsAnalysisRuntimeRecorder $recorder */
        $recorder = app(NewsAnalysisRuntimeRecorder::class);

        $profile = new AiProviderProfile(
            id: 1,
            slug: 'chatgpt-default',
            provider: 'chatgpt_codex',
            displayName: 'ChatGPT Codex',
            enabled: true,
            codexHomeSubpath: 'chatgpt-default',
            defaultModel: 'gpt-5.4-mini',
            maxParallelJobs: 1,
            authStatus: AiProviderProfile::STATUS_AUTHENTICATED,
            defaultReasoningEffort: 'high',
        );

        $recorder->queue((int) $item->getKey());
        $recorder->markRunning((int) $item->getKey(), $profile);
        $recorder->markFallback((int) $item->getKey(), 'rate_limited', 'Weekly limit reached', $profile);
        $recorder->markCompleted((int) $item->getKey());

        $item->refresh();
        $runtime = $item->source_metadata['analysis_runtime'] ?? null;

        $this->assertIsArray($runtime);
        $this->assertSame('fallback', $runtime['status']);
        $this->assertSame(1, $runtime['attempt']);
        $this->assertSame('chatgpt_codex', $runtime['provider']);
        $this->assertSame('gpt-5.4-mini', $runtime['model']);
        $this->assertSame('high', $runtime['reasoning_effort']);
        $this->assertSame('rate_limited', $runtime['fallback_reason']);
        $this->assertSame('Weekly limit reached', $runtime['last_error']);
        $this->assertNotNull($runtime['queued_at']);
        $this->assertNotNull($runtime['started_at']);
        $this->assertNotNull($runtime['finished_at']);
        $this->assertIsArray($runtime['timeline']);
        $this->assertSame('pipeline.queued', $runtime['timeline'][0]['key']);
        $this->assertSame('pipeline.finished', $runtime['timeline'][count($runtime['timeline']) - 1]['key']);
    }

    public function test_queue_starts_new_attempt_and_clears_previous_error_context(): void
    {
        $item = $this->createNewsItem([
            'source_metadata' => [
                'analysis_runtime' => [
                    'status' => 'failed',
                    'attempt' => 2,
                    'queued_at' => now()->subMinute()->toIso8601String(),
                    'last_error' => 'Old error',
                    'fallback_reason' => 'provider_error',
                    'timeline' => [
                        ['key' => 'pipeline.failed', 'label' => 'Pipeline failed', 'status' => 'failed', 'at' => now()->subMinute()->toIso8601String(), 'message' => 'Old error'],
                    ],
                ],
            ],
        ]);

        /** @var NewsAnalysisRuntimeRecorder $recorder */
        $recorder = app(NewsAnalysisRuntimeRecorder::class);
        $recorder->queue((int) $item->getKey());

        $item->refresh();
        $runtime = $item->source_metadata['analysis_runtime'] ?? null;

        $this->assertIsArray($runtime);
        $this->assertSame('queued', $runtime['status']);
        $this->assertSame(3, $runtime['attempt']);
        $this->assertNull($runtime['last_error']);
        $this->assertNull($runtime['fallback_reason']);
        $this->assertCount(1, $runtime['timeline']);
        $this->assertSame('pipeline.queued', $runtime['timeline'][0]['key']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createNewsItem(array $overrides = []): NewsItem
    {
        $source = Source::query()->create([
            'name' => 'Tech Feed',
            'url' => 'https://example.com/rss.xml',
            'type' => 'rss',
            'language_default' => 'en',
            'is_active' => true,
            'error_streak' => 0,
        ]);

        /** @var NewsItem $item */
        $item = NewsItem::query()->create(array_merge([
            'source_id' => $source->getKey(),
            'title_original' => 'Original title',
            'content_original' => 'Original content',
            'status' => NewsStatus::PROCESSING->value,
            'raw_fingerprint' => 'fp-runtime-1',
            'source_metadata' => [],
        ], $overrides));

        return $item;
    }
}
