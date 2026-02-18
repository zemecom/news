<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Database\Seeders\NewsItemSeeder;
use Database\Seeders\SourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
                        'media',
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
                    'media',
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
}
