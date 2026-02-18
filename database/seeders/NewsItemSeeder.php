<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class NewsItemSeeder extends Seeder
{
    public function run(): void
    {
        $sourceId = (int) DB::table('sources')->value('id');

        if ($sourceId === 0) {
            return;
        }

        $items = [
            [
                // 'id' => Str::uuid7()->toString(),
                'source_id' => $sourceId,
                'title_original' => 'Laravel 12 Released with New Features',
                'content_original' => 'Laravel 12 introduces several new features and improvements.',
                'title_generated' => null,
                'content_translated' => null,
                'sentiment_score' => 4,
                'tags' => json_encode(['laravel']),
                'is_important' => false,
                'status' => 'published',
                'source_metadata' => json_encode(['link' => 'https://example.com/laravel-12', 'external_id' => 'seed-1', 'language' => 'en']),
                'raw_fingerprint' => 'seed-fp-1',
                'moderation_reason' => null,
                'published_at' => now()->subHours(1)->toIso8601String(),
                'image_url' => null,
                'media' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                // 'id' => Str::uuid7()->toString(),
                'source_id' => $sourceId,
                'title_original' => 'Breaking: Major Tech Announcement',
                'content_original' => 'A major tech company has made an important announcement.',
                'title_generated' => 'Massive Tech News',
                'content_translated' => 'Крупная компания сделала важное объявление.',
                'sentiment_score' => 7,
                'tags' => json_encode(['it']),
                'is_important' => true,
                'status' => 'published',
                'source_metadata' => json_encode(['link' => 'https://example.com/tech-news', 'external_id' => 'seed-2', 'language' => 'en']),
                'raw_fingerprint' => 'seed-fp-2',
                'moderation_reason' => null,
                'published_at' => now()->subHours(2)->toIso8601String(),
                'image_url' => 'https://example.com/image.jpg',
                'media' => json_encode([['url' => 'https://example.com/image.jpg', 'type' => 'image/jpeg']]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                // 'id' => Str::uuid7()->toString(),
                'source_id' => $sourceId,
                'title_original' => 'Economy Update: Markets Rally',
                'content_original' => 'Stock markets have rallied following positive economic data.',
                'title_generated' => null,
                'content_translated' => null,
                'sentiment_score' => 5,
                'tags' => json_encode(['economy', 'markets']),
                'is_important' => false,
                'status' => 'published',
                'source_metadata' => json_encode(['link' => 'https://example.com/economy', 'external_id' => 'seed-3', 'language' => 'en']),
                'raw_fingerprint' => 'seed-fp-3',
                'moderation_reason' => null,
                'published_at' => now()->subHours(3)->toIso8601String(),
                'image_url' => null,
                'media' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('news_items')->insert($items);
    }
}
