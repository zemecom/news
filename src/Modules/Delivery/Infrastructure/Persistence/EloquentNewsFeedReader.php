<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\Cursor;
use Modules\Delivery\Domain\Contracts\NewsFeedReader;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;
use Modules\Shared\Domain\Enum\NewsStatus;

final class EloquentNewsFeedReader implements NewsFeedReader
{
    public function __construct(private DatabaseManager $db) {}

    /**
     * @return CursorPaginator<int, array<string, mixed>>
     */
    public function paginatePublished(NewsFeedFilters $filters, int $perPage, ?string $cursor): CursorPaginator
    {
        $query = $this->baseQuery($filters);
        $paginator = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                perPage: max(1, min($perPage, 100)),
                columns: [
                    'id',
                    'source_id',
                    'title_original',
                    'content_original',
                    'title_generated',
                    'content_translated',
                    'image_url',
                    'media',
                    'sentiment_score',
                    'tags',
                    'is_important',
                    'status',
                    'source_metadata',
                    'raw_fingerprint',
                    'moderation_reason',
                    'published_at',
                ],
                cursor: $this->decodeCursor($cursor),
            );

        return $paginator->through(
            fn (object $row): array => $this->mapRow($row),
        );
    }

    public function findPublishedById(string $id): ?array
    {
        $row = $this->db->connection()
            ->table('news_items')
            ->where('status', NewsStatus::PUBLISHED->value)
            ->where('id', $id)
            ->first([
                'id',
                'source_id',
                'title_original',
                'content_original',
                'title_generated',
                'content_translated',
                'image_url',
                'media',
                'sentiment_score',
                'tags',
                'is_important',
                'status',
                'source_metadata',
                'raw_fingerprint',
                'moderation_reason',
                'published_at',
            ]);

        return $row === null ? null : $this->mapRow($row);
    }

    private function baseQuery(NewsFeedFilters $filters): Builder
    {
        $query = $this->db->connection()
            ->table('news_items')
            ->where('status', NewsStatus::PUBLISHED->value);

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

        if ($filters->dateFrom !== null) {
            $query->where('published_at', '>=', $filters->dateFrom->toIso8601String());
        }

        if ($filters->dateTo !== null) {
            $query->where('published_at', '<=', $filters->dateTo->toIso8601String());
        }

        if ($filters->query !== null && $filters->query !== '') {
            $driver = $this->db->connection()->getDriverName();
            $operator = $driver === 'pgsql' ? 'ILIKE' : 'LIKE';
            $search = '%'.$filters->query.'%';
            $query->where(function (Builder $nested) use ($operator, $search): void {
                $nested
                    ->where('title_original', $operator, $search)
                    ->orWhere('title_generated', $operator, $search);
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

    /** @return array<string, mixed> */
    private function mapRow(object $row): array
    {
        /** @var array<string, mixed> $rowData */
        $rowData = get_object_vars($row);

        return [
            'id' => (string) ($rowData['id'] ?? ''),
            'source_id' => (int) ($rowData['source_id'] ?? 0),
            'title_original' => (string) ($rowData['title_original'] ?? ''),
            'content_original' => (string) ($rowData['content_original'] ?? ''),
            'title_generated' => isset($rowData['title_generated']) ? (string) $rowData['title_generated'] : null,
            'content_translated' => isset($rowData['content_translated']) ? (string) $rowData['content_translated'] : null,
            'image_url' => isset($rowData['image_url']) ? (string) $rowData['image_url'] : null,
            'media' => $this->decodeJsonArray($rowData['media'] ?? null),
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
}
