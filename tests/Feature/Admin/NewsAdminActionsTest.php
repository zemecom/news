<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\News\Pages\ListNews;
use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Intelligence\Application\Listeners\ProcessRawNewsListener;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class NewsAdminActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_action_column_queues_listener_and_sets_runtime_to_queued(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $news = $this->createNewsItem('fp-record-action');

        $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            ->call('callTableColumnAction', 'ai_analysis_action', (string) $news->getKey());

        Queue::assertPushedOn(
            'intelligence_tasks',
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessRawNewsListener::class
        );

        $news->refresh();
        $runtime = $news->source_metadata['analysis_runtime'] ?? null;

        $this->assertIsArray($runtime);
        $this->assertSame('queued', $runtime['status']);
        $this->assertSame(1, $runtime['attempt']);
    }

    public function test_ai_action_column_serializes_listener_payload_when_queued(): void
    {
        config()->set('queue.default', 'database');

        $admin = $this->createAdmin();
        $news = $this->createNewsItem('fp-record-serialization');

        $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            ->call('callTableColumnAction', 'ai_analysis_action', (string) $news->getKey());

        $this->assertDatabaseCount('jobs', 1);

        $payload = DB::table('jobs')->value('payload');

        $this->assertIsString($payload);
        $this->assertStringContainsString('ProcessRawNewsListener', $payload);
    }

    public function test_reanalyze_selected_bulk_action_queues_each_selected_news(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $first = $this->createNewsItem('fp-bulk-1');
        $second = $this->createNewsItem('fp-bulk-2');

        $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            /** @phpstan-ignore-next-line */
            ->callTableBulkAction('reanalyze_selected', [$first, $second]);

        Queue::assertPushedOn(
            'intelligence_tasks',
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessRawNewsListener::class
        );
        Queue::assertPushedTimes(CallQueuedListener::class, 2);

        $first->refresh();
        $second->refresh();

        $this->assertSame('queued', $first->source_metadata['analysis_runtime']['status'] ?? null);
        $this->assertSame('queued', $second->source_metadata['analysis_runtime']['status'] ?? null);
    }

    public function test_enrich_missing_ai_metadata_action_only_queues_news_without_ai_analysis(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $missingAi = $this->createNewsItem('fp-missing-ai');
        $alreadyAnalyzed = $this->createNewsItem('fp-with-ai', [
            'source_metadata' => [
                'link' => 'https://example.com/news/fp-with-ai',
                'language' => 'en',
                'external_id' => 'ext-fp-with-ai',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                    'model' => 'gpt-5.4-mini',
                ],
            ],
        ]);

        $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            /** @phpstan-ignore-next-line */
            ->callAction('enrich_missing_ai_metadata');

        Queue::assertPushedTimes(CallQueuedListener::class, 1);
        Queue::assertPushedOn(
            'intelligence_tasks',
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessRawNewsListener::class
        );

        $missingAi->refresh();
        $alreadyAnalyzed->refresh();

        $this->assertSame('queued', $missingAi->source_metadata['analysis_runtime']['status'] ?? null);
        $this->assertArrayNotHasKey('analysis_runtime', $alreadyAnalyzed->source_metadata ?? []);
    }

    public function test_refresh_ai_metadata_action_queues_all_filtered_news_including_already_analyzed(): void
    {
        Queue::fake();

        $admin = $this->createAdmin();
        $missingAi = $this->createNewsItem('fp-refresh-missing-ai');
        $alreadyAnalyzed = $this->createNewsItem('fp-refresh-with-ai', [
            'source_metadata' => [
                'link' => 'https://example.com/news/fp-refresh-with-ai',
                'language' => 'en',
                'external_id' => 'ext-fp-refresh-with-ai',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                    'model' => 'gpt-5.4-mini',
                ],
            ],
        ]);

        $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            /** @phpstan-ignore-next-line */
            ->callAction('refresh_ai_metadata');

        Queue::assertPushedTimes(CallQueuedListener::class, 2);
        Queue::assertPushedOn(
            'intelligence_tasks',
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessRawNewsListener::class
        );

        $missingAi->refresh();
        $alreadyAnalyzed->refresh();

        $this->assertSame('queued', $missingAi->source_metadata['analysis_runtime']['status'] ?? null);
        $this->assertSame('queued', $alreadyAnalyzed->source_metadata['analysis_runtime']['status'] ?? null);
    }

    private function createAdmin(): User
    {
        /** @var User $user */
        $user = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createNewsItem(string $fingerprint, array $overrides = []): NewsItem
    {
        $source = Source::query()->firstOrCreate([
            'url' => 'https://example.com/rss.xml',
        ], [
            'name' => 'Tech Feed',
            'type' => 'rss',
            'language_default' => 'en',
            'is_active' => true,
            'error_streak' => 0,
        ]);

        /** @var NewsItem $item */
        $item = NewsItem::query()->create(array_merge([
            'source_id' => $source->getKey(),
            'title_original' => 'Original title '.$fingerprint,
            'content_original' => 'Original content '.$fingerprint,
            'status' => NewsStatus::PUBLISHED->value,
            'raw_fingerprint' => $fingerprint,
            'published_at' => now()->subHour(),
            'source_metadata' => [
                'link' => 'https://example.com/news/'.$fingerprint,
                'language' => 'en',
                'external_id' => 'ext-'.$fingerprint,
            ],
        ], $overrides));

        return $item;
    }
}
