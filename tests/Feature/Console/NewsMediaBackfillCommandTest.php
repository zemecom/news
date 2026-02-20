<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Modules\Catalog\Application\Jobs\PreloadNewsMediaJob;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsMediaAsset;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Tests\TestCase;

final class NewsMediaBackfillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_dispatches_only_items_without_existing_assets_by_default(): void
    {
        Queue::fake();

        $sourceId = $this->createSourceId();

        $withoutAssetsId = $this->createNewsItem($sourceId, 'fp-without-assets', 'https://example.com/new-cover.jpg');
        $withAssetsId = $this->createNewsItem($sourceId, 'fp-with-assets', 'https://example.com/existing-cover.jpg');
        $this->createNewsItem($sourceId, 'fp-no-media', null);

        NewsMediaAsset::query()->create([
            'news_item_id' => $withAssetsId,
            'slot' => 'cover',
            'position' => 0,
            'source_url' => 'https://example.com/existing-cover.jpg',
            'local_disk' => 'public',
            'download_status' => 'downloaded',
        ]);

        self::assertSame(0, Artisan::call('news:media:backfill'));

        Queue::assertPushed(
            PreloadNewsMediaJob::class,
            fn (PreloadNewsMediaJob $job): bool => $job->newsItemId === $withoutAssetsId
        );
        Queue::assertNotPushed(
            PreloadNewsMediaJob::class,
            fn (PreloadNewsMediaJob $job): bool => $job->newsItemId === $withAssetsId
        );
        Queue::assertPushedTimes(PreloadNewsMediaJob::class, 1);
    }

    public function test_it_can_reprocess_all_items_with_all_flag(): void
    {
        Queue::fake();

        $sourceId = $this->createSourceId();

        $withoutAssetsId = $this->createNewsItem($sourceId, 'fp-all-without-assets', 'https://example.com/new-cover.jpg');
        $withAssetsId = $this->createNewsItem($sourceId, 'fp-all-with-assets', 'https://example.com/existing-cover.jpg');

        NewsMediaAsset::query()->create([
            'news_item_id' => $withAssetsId,
            'slot' => 'cover',
            'position' => 0,
            'source_url' => 'https://example.com/existing-cover.jpg',
            'local_disk' => 'public',
            'download_status' => 'downloaded',
        ]);

        self::assertSame(0, Artisan::call('news:media:backfill --all'));

        Queue::assertPushed(
            PreloadNewsMediaJob::class,
            fn (PreloadNewsMediaJob $job): bool => $job->newsItemId === $withoutAssetsId
        );
        Queue::assertPushed(
            PreloadNewsMediaJob::class,
            fn (PreloadNewsMediaJob $job): bool => $job->newsItemId === $withAssetsId
        );
        Queue::assertPushedTimes(PreloadNewsMediaJob::class, 2);
    }

    private function createSourceId(): int
    {
        $source = Source::query()->create([
            'name' => 'Test Source',
            'url' => 'https://example.com/feed.xml',
            'type' => 'rss',
            'language_default' => 'en',
            'cron_expression' => '* * * * *',
            'is_active' => true,
        ]);

        return (int) $source->getKey();
    }

    private function createNewsItem(int $sourceId, string $fingerprint, ?string $imageUrl): int
    {
        $item = NewsItem::query()->create([
            'source_id' => $sourceId,
            'title_original' => 'Backfill test item',
            'content_original' => 'Backfill test content',
            'status' => 'published',
            'raw_fingerprint' => $fingerprint,
            'image_url' => $imageUrl,
            'media' => $imageUrl !== null
                ? [['url' => $imageUrl, 'type' => 'image/jpeg']]
                : [],
        ]);

        return (int) $item->id;
    }
}
