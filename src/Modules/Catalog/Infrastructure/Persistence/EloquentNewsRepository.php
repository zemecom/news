<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Support\Str;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;

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
        // $id = $raw->rawId ?? Str::uuid()->toString();

        $item = NewsItem::query()->firstOrCreate(
            ['raw_fingerprint' => $raw->fingerprint],
            [
                // 'id' => $id,
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
        NewsItem::query()
            ->whereKey($enriched->rawId)
            ->update([
                'title_generated' => $enriched->titleGenerated,
                'content_translated' => $enriched->contentTranslated,
                'sentiment_score' => $enriched->sentiment,
                'tags' => $enriched->tags,
                'is_important' => $enriched->importance,
                'status' => $enriched->status->value,
                'moderation_reason' => $enriched->moderationReason,
            ]);
    }
}
