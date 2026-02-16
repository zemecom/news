<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Shared\Domain\Enum\NewsStatus;

final class NewsItemSeeder extends Seeder
{
    public function run(): void
    {
        $now = CarbonImmutable::now();

        $hnId = \Modules\Catalog\Infrastructure\Persistence\Models\Source::query()->where('name', 'Hacker News')->value('id') ?? 1;
        $tcId = \Modules\Catalog\Infrastructure\Persistence\Models\Source::query()->where('name', 'TechCrunch')->value('id') ?? 2;
        $atId = \Modules\Catalog\Infrastructure\Persistence\Models\Source::query()->where('name', 'Ars Technica')->value('id') ?? 4;

        $items = [
            [
                'source_id' => $hnId,
                'title_original' => 'New AI model released',
                'content_original' => 'A new AI model has been announced with promising benchmarks.',
                'title_generated' => 'Вышла новая модель ИИ',
                'content_translated' => 'Объявлена новая модель ИИ с многообещающими результатами.',
                'sentiment_score' => 6,
                'tags' => ['ai', 'ml', 'release'],
                'is_important' => true,
                'status' => NewsStatus::PUBLISHED->value,
                'source_metadata' => ['external_id' => 'ai-001', 'link' => 'https://example.com/ai-001', 'language' => 'en'],
                'raw_fingerprint' => 'fp-ai-001',
                'published_at' => $now->subMinutes(30),
                'image_url' => 'https://picsum.photos/seed/ai/800/400',
                'media' => [
                    ['url' => 'https://picsum.photos/seed/ai/800/400', 'type' => 'image/jpeg'],
                    ['url' => 'https://example.com/video/ai-001.mp4', 'type' => 'video/mp4'],
                ],
            ],
            [
                'source_id' => $tcId,
                'title_original' => 'Market reacts to rate changes',
                'content_original' => 'Markets showed mixed reaction to recent rate adjustments.',
                'title_generated' => 'Рынок реагирует на изменения ставок',
                'content_translated' => 'Рынки показали смешанную реакцию на недавние изменения ставок.',
                'sentiment_score' => -1,
                'tags' => ['economy', 'markets'],
                'is_important' => true,
                'status' => NewsStatus::PUBLISHED->value,
                'source_metadata' => ['external_id' => 'eco-010', 'link' => 'https://example.com/eco-010', 'language' => 'en'],
                'raw_fingerprint' => 'fp-eco-010',
                'published_at' => $now->subHours(1),
                'image_url' => 'https://picsum.photos/seed/market/800/400',
                'media' => [
                    ['url' => 'https://picsum.photos/seed/market/800/400', 'type' => 'image/jpeg'],
                ],
            ],
            [
                'source_id' => $atId,
                'title_original' => 'Новая версия Laravel вышла',
                'content_original' => 'Laravel представил новую версию с улучшенной производительностью.',
                'title_generated' => 'Laravel обновился',
                'content_translated' => 'Laravel представил новую версию с улучшенной производительностью.',
                'sentiment_score' => 4,
                'tags' => ['laravel', 'release', 'php'],
                'is_important' => false,
                'status' => NewsStatus::PUBLISHED->value,
                'source_metadata' => ['external_id' => 'it-777', 'link' => 'https://example.com/it-777', 'language' => 'ru'],
                'raw_fingerprint' => 'fp-it-777',
                'published_at' => $now->subMinutes(10),
                'image_url' => 'https://picsum.photos/seed/laravel/800/400',
                'media' => [
                    ['url' => 'https://picsum.photos/seed/laravel/800/400', 'type' => 'image/jpeg'],
                ],
            ],
        ];

        foreach ($items as $item) {
            NewsItem::query()->updateOrCreate(
                ['raw_fingerprint' => $item['raw_fingerprint']],
                array_merge(
                    [
                        'id' => Str::uuid()->toString(),
                    ],
                    $item,
                ),
            );
        }
    }
}
