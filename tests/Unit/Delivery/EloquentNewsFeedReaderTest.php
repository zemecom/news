<?php

declare(strict_types=1);

namespace Tests\Unit\Delivery;

use Carbon\CarbonImmutable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;
use Modules\Delivery\Infrastructure\Persistence\EloquentNewsFeedReader;
use Tests\TestCase;

final class EloquentNewsFeedReaderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_find_published_by_id_uses_resolved_media_when_available(): void
    {
        $source = $this->createSource();
        $newsItem = $this->createNewsItem($source, [
            'title_original' => 'News with resolved media',
            'image_url' => 'https://example.com/original-cover.jpg',
            'media' => [
                ['url' => 'https://example.com/gallery.jpg', 'type' => 'image/jpeg'],
            ],
        ]);

        $resolver = $this->createMock(NewsMediaResolver::class);
        $newsItemId = (int) $newsItem->getKey();
        $resolver->expects($this->once())
            ->method('resolveForNewsItems')
            ->with([$newsItemId])
            ->willReturn([
                $newsItemId => [
                    'image_url' => 'https://cdn.example.com/cover.jpg',
                    'image_url_original' => 'https://example.com/original-cover.jpg',
                    'image_url_local' => 'https://cdn.example.com/cover.jpg',
                    'media' => [
                        ['url' => 'https://cdn.example.com/gallery.jpg', 'type' => 'image/jpeg'],
                    ],
                    'media_original' => [
                        ['url' => 'https://example.com/gallery.jpg', 'type' => 'image/jpeg'],
                    ],
                    'media_local' => [
                        ['url' => 'https://cdn.example.com/gallery.jpg', 'type' => 'image/jpeg'],
                    ],
                ],
            ]);

        $reader = new EloquentNewsFeedReader(app(DatabaseManager::class), $resolver);
        $result = $reader->findPublishedById((string) $newsItemId);

        $this->assertIsArray($result);
        $this->assertSame('https://cdn.example.com/cover.jpg', $result['image_url']);
        $this->assertSame('https://cdn.example.com/cover.jpg', $result['image_url_local']);
        $this->assertSame([
            ['url' => 'https://cdn.example.com/gallery.jpg', 'type' => 'image/jpeg'],
        ], $result['media']);
    }

    public function test_find_published_by_id_falls_back_to_original_media_when_resolver_is_empty(): void
    {
        $source = $this->createSource();
        $newsItem = $this->createNewsItem($source, [
            'title_original' => 'Fallback media item',
            'image_url' => 'https://example.com/original-cover.jpg',
            'media' => [
                ['url' => 'https://example.com/gallery.jpg', 'type' => 'image/jpeg'],
                'https://example.com/video.mp4',
            ],
        ]);

        $resolver = $this->createMock(NewsMediaResolver::class);
        $newsItemId = (int) $newsItem->getKey();
        $resolver->expects($this->once())
            ->method('resolveForNewsItems')
            ->with([$newsItemId])
            ->willReturn([]);

        $reader = new EloquentNewsFeedReader(app(DatabaseManager::class), $resolver);
        $result = $reader->findPublishedById((string) $newsItemId);

        $this->assertIsArray($result);
        $this->assertSame('https://example.com/original-cover.jpg', $result['image_url']);
        $this->assertSame('https://example.com/original-cover.jpg', $result['image_url_original']);
        $this->assertNull($result['image_url_local']);
        $this->assertSame([
            ['url' => 'https://example.com/gallery.jpg', 'type' => 'image/jpeg'],
            ['url' => 'https://example.com/video.mp4', 'type' => null],
        ], $result['media']);
        $this->assertSame($result['media'], $result['media_original']);
        $this->assertSame([], $result['media_local']);
    }

    public function test_paginate_published_uses_cursor_order(): void
    {
        $source = $this->createSource();
        $sourceId = (int) $source->getKey();
        $newest = $this->createNewsItem($source, [
            'title_original' => 'Newest item',
            'published_at' => CarbonImmutable::parse('2026-03-17T11:00:00+00:00'),
        ]);
        $older = $this->createNewsItem($source, [
            'title_original' => 'Older item',
            'published_at' => CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
        ]);

        $resolver = $this->createMock(NewsMediaResolver::class);
        $resolver->method('resolveForNewsItems')->willReturn([]);

        $reader = new EloquentNewsFeedReader(app(DatabaseManager::class), $resolver);
        $filters = new NewsFeedFilters(sourceId: $sourceId);

        $firstPage = $reader->paginatePublished($filters, 1, null);
        $this->assertSame((string) $newest->getKey(), $firstPage->items()[0]['id']);

        $nextCursor = $firstPage->nextCursor();
        $this->assertNotNull($nextCursor);

        $secondPage = $reader->paginatePublished($filters, 1, $nextCursor->encode());
        $this->assertSame((string) $older->getKey(), $secondPage->items()[0]['id']);
    }

    public function test_count_applies_filters_to_source_and_search_terms(): void
    {
        $source = $this->createSource();
        $this->createNewsItem($source, [
            'title_original' => 'Not matching item',
            'title_generated' => 'Other bulletin',
            'tags' => ['php'],
            'is_important' => false,
            'sentiment_score' => 1,
            'published_at' => CarbonImmutable::parse('2026-03-15T10:00:00+00:00'),
        ]);
        $matching = $this->createNewsItem($source, [
            'title_original' => 'Ignored original title',
            'title_generated' => 'Growth bulletin',
            'tags' => ['laravel'],
            'is_important' => true,
            'sentiment_score' => 4,
            'published_at' => CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
        ]);

        $resolver = $this->createMock(NewsMediaResolver::class);
        $resolver->method('resolveForNewsItems')->willReturn([]);

        $reader = new EloquentNewsFeedReader(app(DatabaseManager::class), $resolver);
        $sourceId = (int) $source->getKey();
        $matchingId = (string) $matching->getKey();
        $filters = new NewsFeedFilters(
            category: 'laravel',
            sentimentMin: 3,
            sentimentMax: 5,
            important: true,
            dateFrom: CarbonImmutable::parse('2026-03-17T00:00:00+00:00'),
            dateTo: CarbonImmutable::parse('2026-03-18T00:00:00+00:00'),
            query: 'bulletin',
            sourceId: $sourceId,
        );

        $this->assertSame(1, $reader->count($filters));
        $found = $reader->findPublishedById($matchingId);
        $this->assertIsArray($found);
        $this->assertSame($matchingId, $found['id']);
    }

    private function createSource(): Source
    {
        /** @var Source $source */
        $source = Source::query()->create([
            'name' => 'Delivery Test Source',
            'url' => 'https://example.com/feed.xml',
            'type' => 'rss',
            'language_default' => 'en',
            'cron_expression' => '* * * * *',
            'is_active' => true,
        ]);

        return $source;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createNewsItem(Source $source, array $overrides = []): NewsItem
    {
        /** @var NewsItem $newsItem */
        $newsItem = NewsItem::query()->create(array_merge([
            'source_id' => $source->getKey(),
            'title_original' => 'News item',
            'content_original' => 'News item content',
            'title_generated' => null,
            'content_translated' => null,
            'image_url' => null,
            'media' => [],
            'sentiment_score' => 0,
            'tags' => [],
            'is_important' => false,
            'status' => 'published',
            'source_metadata' => [],
            'raw_fingerprint' => 'fp-'.Str::uuid()->toString(),
            'moderation_reason' => null,
            'published_at' => CarbonImmutable::parse('2026-03-17T09:00:00+00:00'),
        ], $overrides));

        return $newsItem;
    }
}
