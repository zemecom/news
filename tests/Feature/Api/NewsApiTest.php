<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Carbon\CarbonImmutable;
use Database\Seeders\NewsItemSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Tests\TestCase;

final class NewsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->seed(NewsItemSeeder::class);
    }

    public function test_index_returns_paginated_news_feed(): void
    {
        $response = $this->getJson('/api/news?per_page=2');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'title_original',
                        'title_generated',
                        'content_original',
                        'content_translated',
                        'image_url',
                        'image_url_original',
                        'image_url_local',
                        'media',
                        'media_original',
                        'media_local',
                        'sentiment',
                        'tags',
                        'important',
                        'status',
                        'published_at',
                    ],
                ],
                'meta' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                ],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_index_applies_filters(): void
    {
        $response = $this->getJson('/api/news?category=laravel&important=0&sentiment_min=3&sentiment_max=5');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tags.0', 'laravel')
            ->assertJsonPath('data.0.important', false);
    }

    public function test_show_returns_single_news_item(): void
    {
        $id = (string) DB::table('news_items')->value('id');

        $this->getJson("/api/news/{$id}")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'source_id',
                    'title_original',
                    'title_generated',
                    'image_url',
                    'image_url_original',
                    'image_url_local',
                    'media',
                    'media_original',
                    'media_local',
                    'raw_fingerprint',
                ],
            ])
            ->assertJsonPath('data.id', $id);
    }

    public function test_show_returns_404_for_unknown_news_item(): void
    {
        $this->getJson('/api/news/99999999')
            ->assertNotFound();
    }

    public function test_sources_returns_active_sources_list(): void
    {
        $this->getJson('/api/sources')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ]);
    }

    public function test_index_rejects_invalid_sentiment_range(): void
    {
        $this->getJson('/api/news?sentiment_min=5&sentiment_max=3')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sentiment_range']);
    }

    public function test_index_rejects_invalid_date_range(): void
    {
        $this->getJson('/api/news?date_from=2026-03-18&date_to=2026-03-17')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_range']);
    }

    public function test_index_rejects_invalid_source_id(): void
    {
        $this->getJson('/api/news?source_id=abc')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source_id']);
    }

    public function test_index_filters_by_source_query_and_date_range(): void
    {
        $source = $this->createSource('Filter Source');
        $matchingItem = $this->createNewsItem($source, [
            'title_original' => 'Breaking local news',
            'title_generated' => 'Growth bulletin',
            'content_original' => 'Content about growth',
            'tags' => ['laravel'],
            'is_important' => true,
            'sentiment_score' => 4,
            'published_at' => CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
        ]);
        $this->createNewsItem($source, [
            'title_original' => 'Other source update',
            'title_generated' => 'Different bulletin',
            'content_original' => 'Content about something else',
            'tags' => ['php'],
            'is_important' => false,
            'sentiment_score' => 1,
            'published_at' => CarbonImmutable::parse('2026-03-15T10:00:00+00:00'),
        ]);

        $response = $this->getJson(sprintf(
            '/api/news?source_id=%d&q=bulletin&date_from=2026-03-17&date_to=2026-03-17&per_page=5',
            $source->getKey(),
        ));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $matchingItem->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_index_uses_cursor_pagination_between_pages(): void
    {
        $source = $this->createSource('Cursor Source');
        $newest = $this->createNewsItem($source, [
            'title_original' => 'Newest item',
            'published_at' => CarbonImmutable::parse('2026-03-17T11:00:00+00:00'),
        ]);
        $older = $this->createNewsItem($source, [
            'title_original' => 'Older item',
            'published_at' => CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
        ]);

        $firstPage = $this->getJson(sprintf('/api/news?source_id=%d&per_page=1', $source->getKey()));
        $firstPage
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $newest->id);

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        $secondPage = $this->getJson(sprintf(
            '/api/news?source_id=%d&per_page=1&cursor=%s',
            $source->getKey(),
            urlencode((string) $cursor),
        ));

        $secondPage
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $older->id);
    }

    private function createSource(string $name): Source
    {
        /** @var Source $source */
        $source = Source::query()->create([
            'name' => $name,
            'url' => 'https://example.com/'.Str::slug($name).'.xml',
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
            'raw_fingerprint' => Str::uuid()->toString(),
            'moderation_reason' => null,
            'published_at' => CarbonImmutable::parse('2026-03-17T09:00:00+00:00'),
        ], $overrides));

        return $newsItem;
    }
}
