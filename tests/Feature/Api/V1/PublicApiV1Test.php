<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Carbon\CarbonImmutable;
use Database\Seeders\NewsItemSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Tests\TestCase;

final class PublicApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SourceSeeder::class);
        $this->seed(NewsItemSeeder::class);
    }

    public function test_v1_news_returns_paginated_resource_collection(): void
    {
        $this->getJson('/api/v1/news?per_page=2')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source',
                        'title',
                        'excerpt',
                        'sentiment',
                        'tags',
                        'important',
                        'status',
                        'published_at',
                        'media',
                    ],
                ],
                'meta' => [
                    'per_page',
                    'next_cursor',
                    'prev_cursor',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_v1_news_detail_returns_stable_resource_shape(): void
    {
        /** @var NewsItem $newsItem */
        $newsItem = NewsItem::query()->firstOrFail();

        $this->getJson(sprintf('/api/v1/news/%d', $newsItem->getKey()))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'source',
                    'title',
                    'content',
                    'sentiment',
                    'tags',
                    'important',
                    'status',
                    'published_at',
                    'media',
                    'analysis',
                ],
            ])
            ->assertJsonPath('data.id', (string) $newsItem->getKey());
    }

    public function test_v1_news_filters_returns_categories_and_sources(): void
    {
        $this->getJson('/api/v1/news/filters')
            ->assertOk()
            ->assertJsonPath('data.categories.0', 'IT')
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'sources' => [
                        '*' => [
                            'id',
                            'name',
                        ],
                    ],
                    'sentiment_range' => [
                        'min',
                        'max',
                    ],
                ],
            ]);
    }

    public function test_v1_news_supports_filters_and_cursor_pagination(): void
    {
        $source = $this->createSource('API V1 Source');
        $newest = $this->createNewsItem($source, [
            'title_original' => 'Newest item',
            'title_generated' => 'Growth bulletin',
            'tags' => ['laravel'],
            'is_important' => true,
            'sentiment_score' => 4,
            'published_at' => CarbonImmutable::parse('2026-03-17T11:00:00+00:00'),
        ]);
        $older = $this->createNewsItem($source, [
            'title_original' => 'Older item',
            'title_generated' => 'Growth archive',
            'tags' => ['laravel'],
            'is_important' => true,
            'sentiment_score' => 3,
            'published_at' => CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
        ]);

        $firstPage = $this->getJson(sprintf(
            '/api/v1/news?source_id=%d&q=growth&category=laravel&important=1&sentiment_min=3&sentiment_max=5&per_page=1',
            $source->getKey(),
        ));

        $firstPage
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $newest->getKey());

        $cursor = $firstPage->json('meta.next_cursor');
        $this->assertIsString($cursor);

        $this->getJson(sprintf(
            '/api/v1/news?source_id=%d&q=growth&category=laravel&important=1&sentiment_min=3&sentiment_max=5&per_page=1&cursor=%s',
            $source->getKey(),
            urlencode($cursor),
        ))
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $older->getKey());
    }

    public function test_v1_news_validation_errors_use_api_error_envelope(): void
    {
        $this->getJson('/api/v1/news?sentiment_min=5&sentiment_max=3')
            ->assertUnprocessable()
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details',
                ],
            ])
            ->assertJsonPath('error.code', 'validation_failed');
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
