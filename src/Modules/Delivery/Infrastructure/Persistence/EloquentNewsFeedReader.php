<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Cursor;
use Modules\Delivery\Domain\Contracts\NewsFeedReader;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;
use Modules\Shared\Domain\Enum\NewsStatus;

final readonly class EloquentNewsFeedReader implements NewsFeedReader
{
    public function __construct(
        private DatabaseManager $db,
        private NewsMediaResolver $mediaAssets,
    ) {}

    /**
     * @return CursorPaginator<int, array<string, mixed>>
     */
    public function paginatePublished(NewsFeedFilters $filters, int $perPage, ?string $cursor): CursorPaginator
    {
        $query = $this->baseQuery($filters);
        $paginator = $query
            ->orderByDesc('news_items.published_at')
            ->orderByDesc('news_items.id')
            ->cursorPaginate(
                perPage: max(1, min($perPage, 100)),
                columns: [
                    'news_items.id',
                    'news_items.source_id',
                    'sources.name as source_name',
                    'news_items.title_original',
                    'news_items.content_original',
                    'news_items.title_generated',
                    'news_items.content_translated',
                    'news_items.image_url',
                    'news_items.media',
                    'news_items.sentiment_score',
                    'news_items.tags',
                    'news_items.is_important',
                    'news_items.status',
                    'news_items.source_metadata',
                    'news_items.raw_fingerprint',
                    'news_items.moderation_reason',
                    'news_items.published_at',
                ],
                cursor: $this->decodeCursor($cursor),
            );

        $resolvedMedia = $this->mediaAssets->resolveForNewsItems($this->extractNewsItemIds($paginator->items()));

        return $paginator->through(
            fn (object $row): array => $this->mapRow($row, $resolvedMedia[(int) ($row->id ?? 0)] ?? null),
        );
    }

    public function findPublishedById(string $id): ?array
    {
        $row = $this->db->connection()
            ->table('news_items')
            ->where('status', NewsStatus::PUBLISHED->value)
            ->leftJoin('sources', 'sources.id', '=', 'news_items.source_id')
            ->where('news_items.id', $id)
            ->first([
                'news_items.id',
                'news_items.source_id',
                'sources.name as source_name',
                'news_items.title_original',
                'news_items.content_original',
                'news_items.title_generated',
                'news_items.content_translated',
                'news_items.image_url',
                'news_items.media',
                'news_items.sentiment_score',
                'news_items.tags',
                'news_items.is_important',
                'news_items.status',
                'news_items.source_metadata',
                'news_items.raw_fingerprint',
                'news_items.moderation_reason',
                'news_items.published_at',
            ]);

        if ($row === null) {
            return null;
        }

        $resolvedMedia = $this->mediaAssets->resolveForNewsItems([(int) ($row->id ?? 0)]);

        return $this->mapRow($row, $resolvedMedia[(int) ($row->id ?? 0)] ?? null);
    }

    public function count(NewsFeedFilters $filters): int
    {
        return $this->baseQuery($filters)->count();
    }

    private function baseQuery(NewsFeedFilters $filters): Builder
    {
        $query = $this->db->connection()
            ->table('news_items')
            ->leftJoin('sources', 'sources.id', '=', 'news_items.source_id')
            ->where('news_items.status', NewsStatus::PUBLISHED->value);

        if ($filters->sourceId !== null) {
            $query->where('news_items.source_id', $filters->sourceId);
        }

        if ($filters->category !== null && $filters->category !== '') {
            $query->whereJsonContains('tags', $filters->category);
        }

        if ($filters->sentimentMin !== null) {
            $query->where('sentiment_score', '>=', $filters->sentimentMin);
        }

        if ($filters->sentimentMax !== null) {
            $query->where('sentiment_score', '<=', $filters->sentimentMax);
        }

        if ($filters->important !== null) {
            $query->where('is_important', $filters->important);
        }

        if ($filters->dateFrom instanceof \Carbon\CarbonImmutable) {
            $query->where('published_at', '>=', $filters->dateFrom->toIso8601String());
        }

        if ($filters->dateTo instanceof \Carbon\CarbonImmutable) {
            $query->where('published_at', '<=', $filters->dateTo->toIso8601String());
        }

        if ($filters->query !== null && $filters->query !== '') {
            $driver = $this->db->connection()->getDriverName();
            $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $search = '%'.$filters->query.'%';
            $query->where(function (Builder $nested) use ($operator, $search): void {
                $nested
                    ->where('news_items.title_original', $operator, $search)
                    ->orWhere('news_items.title_generated', $operator, $search);
            });
        }

        return $query;
    }

    private function decodeCursor(?string $cursor): ?Cursor
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        return Cursor::fromEncoded($cursor);
    }

    /**
     * @param array{
     *   image_url:?string,
     *   image_url_original:?string,
     *   image_url_local:?string,
     *   media:list<array{url:string, type:?string}>,
     *   media_original:list<array{url:string, type:?string}>,
     *   media_local:list<array{url:string, type:?string}>
     * }|null $resolvedMedia
     * @return array<string, mixed>
     */
    private function mapRow(object $row, ?array $resolvedMedia): array
    {
        /** @var array<string, mixed> $rowData */
        $rowData = get_object_vars($row);
        $fallbackImageUrl = isset($rowData['image_url']) ? (string) $rowData['image_url'] : null;
        $fallbackMediaOriginal = $this->normalizeMediaItems($this->decodeJsonArray($rowData['media'] ?? null));

        $imageUrlOriginal = $resolvedMedia['image_url_original'] ?? $fallbackImageUrl;
        $imageUrlLocal = $resolvedMedia['image_url_local'] ?? null;
        $imageUrl = $resolvedMedia['image_url'] ?? $imageUrlOriginal;
        $mediaOriginal = $resolvedMedia['media_original'] ?? $fallbackMediaOriginal;
        $mediaLocal = $resolvedMedia['media_local'] ?? [];
        $media = $resolvedMedia['media'] ?? $mediaOriginal;

        return [
            'id' => (string) ($rowData['id'] ?? ''),
            'source_id' => (int) ($rowData['source_id'] ?? 0),
            'source_name' => isset($rowData['source_name']) ? (string) $rowData['source_name'] : null,
            'title_original' => (string) ($rowData['title_original'] ?? ''),
            'content_original' => (string) ($rowData['content_original'] ?? ''),
            'title_generated' => isset($rowData['title_generated']) ? (string) $rowData['title_generated'] : null,
            'content_translated' => isset($rowData['content_translated']) ? (string) $rowData['content_translated'] : null,
            'image_url' => $imageUrl,
            'image_url_original' => $imageUrlOriginal,
            'image_url_local' => $imageUrlLocal,
            'media' => $media,
            'media_original' => $mediaOriginal,
            'media_local' => $mediaLocal,
            'sentiment' => (int) ($rowData['sentiment_score'] ?? 0),
            'tags' => $this->decodeJsonArray($rowData['tags'] ?? null),
            'important' => (bool) ($rowData['is_important'] ?? false),
            'status' => (string) ($rowData['status'] ?? ''),
            'source_metadata' => $this->decodeJsonArray($rowData['source_metadata'] ?? null),
            'raw_fingerprint' => (string) ($rowData['raw_fingerprint'] ?? ''),
            'moderation_reason' => isset($rowData['moderation_reason']) ? (string) $rowData['moderation_reason'] : null,
            'published_at' => isset($rowData['published_at']) ? (string) $rowData['published_at'] : null,
        ];
    }

    /**
     * @return array<int|string, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<int|string, mixed>  $media
     * @return list<array{url:string, type:?string}>
     */
    private function normalizeMediaItems(array $media): array
    {
        $normalized = [];

        foreach ($media as $mediaItem) {
            if (is_array($mediaItem)) {
                $url = $mediaItem['url'] ?? null;
                if (! is_string($url) || trim($url) === '') {
                    continue;
                }

                $type = $mediaItem['type'] ?? null;
                $normalized[] = [
                    'url' => trim($url),
                    'type' => is_string($type) && trim($type) !== '' ? trim($type) : null,
                ];

                continue;
            }

            if (is_string($mediaItem) && trim($mediaItem) !== '') {
                $normalized[] = [
                    'url' => trim($mediaItem),
                    'type' => null,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, object>  $rows
     * @return list<int>
     */
    private function extractNewsItemIds(array $rows): array
    {
        /** @var list<int> $ids */
        $ids = array_values(array_filter(array_map(
            static fn (object $row): int => (int) ($row->id ?? 0),
            $rows,
        ), static fn (int $id): bool => $id > 0));

        return array_values(array_unique($ids));
    }
}
