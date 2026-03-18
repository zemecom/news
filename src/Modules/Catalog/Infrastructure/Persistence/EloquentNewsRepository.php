<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence;

use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;

/**
 * Фактическая реализация репозитория для работы с новостями через Eloquent ORM.
 * Модуль: Catalog. Слой: Infrastructure.
 *
 * Инкапсулирует в себе все SQL/PostgreSQL особенности.
 * Для остальных модулей (например, модуля Intelligence) этот класс неизвестен,
 * они общаются исключительно через абстрактный контракт `NewsRepository` (Inversion of Control),
 * что позволяет легко подменять БД или мокать её в тестах.
 */
final class EloquentNewsRepository implements NewsRepository
{
    public function existsByFingerprint(string $fingerprint): bool
    {
        return NewsItem::query()
            ->where('raw_fingerprint', $fingerprint)
            ->exists();
    }

    public function findIdByFingerprint(string $fingerprint): int
    {
        return (int) NewsItem::query()
            ->where('raw_fingerprint', $fingerprint)
            ->value('id');
    }

    public function storeRaw(RawNewsData $raw): int
    {
        $item = NewsItem::query()->firstOrCreate(
            ['raw_fingerprint' => $raw->fingerprint],
            [
                'source_id' => $raw->sourceId,
                'title_original' => $raw->title,
                'content_original' => $raw->content,
                'image_url' => $raw->imageUrl,
                'media' => $raw->media,
                'status' => NewsStatus::PROCESSING->value,
                'source_metadata' => array_merge($raw->metadata, [
                    'external_id' => $raw->externalId,
                    'link' => $raw->link,
                    'language' => $raw->language,
                ]),
                'published_at' => $raw->publishedAt,
            ],
        );

        return (int) $item->id;
    }

    public function storeEnriched(EnrichedNewsData $enriched): void
    {
        /** @var NewsItem $item */
        $item = NewsItem::query()->findOrFail($enriched->rawId);
        /** @var array<string, mixed>|null $sourceMetadata */
        $sourceMetadata = $item->source_metadata;

        $item->update([
            'title_generated' => $enriched->titleGenerated,
            'content_translated' => $enriched->contentTranslated,
            'sentiment_score' => $enriched->sentiment,
            'tags' => $enriched->tags,
            'is_important' => $enriched->importance,
            'status' => $enriched->status->value,
            'moderation_reason' => $enriched->moderationReason,
            'source_metadata' => array_merge($sourceMetadata ?? [], [
                'analysis' => $enriched->analysisMetadata,
            ]),
        ]);
    }

    public function getMediaUrls(int $id): ?array
    {
        /** @var NewsItem|null $item */
        $item = NewsItem::query()->find($id);

        if (! $item) {
            return null;
        }

        /** @var array<int, mixed>|null $mediaRaw */
        $mediaRaw = $item->media;

        return [
            'image_url' => $item->image_url,
            'media' => is_array($mediaRaw) ? array_values($mediaRaw) : [],
        ];
    }
}
