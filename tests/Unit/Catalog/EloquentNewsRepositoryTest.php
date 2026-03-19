<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class EloquentNewsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_raw_by_id_reconstructs_original_payload_for_reanalysis(): void
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
        $item = NewsItem::query()->create([
            'source_id' => $source->getKey(),
            'title_original' => 'Original title',
            'content_original' => 'Original content',
            'title_generated' => 'Generated title',
            'content_translated' => 'Переведённый текст',
            'status' => NewsStatus::PUBLISHED->value,
            'raw_fingerprint' => 'fp-123',
            'published_at' => now()->subHour(),
            'image_url' => 'https://example.com/image.jpg',
            'media' => [
                ['url' => 'https://example.com/image.jpg', 'type' => 'image/jpeg'],
            ],
            'source_metadata' => [
                'external_id' => 'ext-123',
                'link' => 'https://example.com/news/123',
                'language' => 'en',
                'author' => 'John Doe',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                ],
                'analysis_runtime' => [
                    'status' => 'completed',
                ],
            ],
        ]);

        /** @var NewsRepository $repository */
        $repository = app(NewsRepository::class);
        $raw = $repository->findRawById((int) $item->getKey());

        $this->assertNotNull($raw);
        $this->assertSame((int) $item->getKey(), $raw->rawId);
        $this->assertSame((int) $source->getKey(), $raw->sourceId);
        $this->assertSame('ext-123', $raw->externalId);
        $this->assertSame('Original title', $raw->title);
        $this->assertSame('Original content', $raw->content);
        $this->assertSame('https://example.com/news/123', $raw->link);
        $this->assertSame('en', $raw->language);
        $this->assertSame('fp-123', $raw->fingerprint);
        $this->assertSame('https://example.com/image.jpg', $raw->imageUrl);
        $this->assertSame([['url' => 'https://example.com/image.jpg', 'type' => 'image/jpeg']], $raw->media);
        $this->assertSame(['author' => 'John Doe'], $raw->metadata);
        $this->assertArrayNotHasKey('analysis', $raw->metadata);
        $this->assertArrayNotHasKey('analysis_runtime', $raw->metadata);
    }
}
