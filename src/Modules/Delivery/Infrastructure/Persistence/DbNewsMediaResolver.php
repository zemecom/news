<?php

declare(strict_types=1);

namespace Modules\Delivery\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Modules\Delivery\Domain\Contracts\NewsMediaResolver;

final readonly class DbNewsMediaResolver implements NewsMediaResolver
{
    private const string SLOT_COVER = 'cover';

    public function __construct(private DatabaseManager $db) {}

    public function resolveForNewsItems(array $newsItemIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $newsItemIds,
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            return [];
        }

        $resolved = [];
        foreach ($ids as $id) {
            $resolved[$id] = $this->defaultMediaState();
        }

        $rows = $this->db->connection()
            ->table('news_media_assets')
            ->whereIn('news_item_id', $ids)
            ->orderBy('news_item_id')
            ->orderBy('slot')
            ->orderBy('position')
            ->get([
                'news_item_id',
                'slot',
                'source_url',
                'source_mime_type',
                'local_disk',
                'local_path',
                'downloaded_mime_type',
            ]);

        foreach ($rows as $row) {
            /** @var array<string, mixed> $rowData */
            $rowData = get_object_vars($row);
            $newsItemId = (int) ($rowData['news_item_id'] ?? 0);

            if ($newsItemId <= 0) {
                continue;
            }

            if (! array_key_exists($newsItemId, $resolved)) {
                $resolved[$newsItemId] = $this->defaultMediaState();
            }

            $originalUrl = $this->normalizeString($rowData['source_url'] ?? null);
            if ($originalUrl === null) {
                continue;
            }

            $localUrl = $this->resolveLocalUrl(
                $this->normalizeString($rowData['local_disk'] ?? null) ?? 'public',
                $this->normalizeString($rowData['local_path'] ?? null),
            );
            $sourceType = $this->normalizeString($rowData['source_mime_type'] ?? null);
            $effectiveType = $this->normalizeString($rowData['downloaded_mime_type'] ?? null) ?? $sourceType;
            $slot = (string) ($rowData['slot'] ?? '');

            if ($slot === self::SLOT_COVER) {
                $resolved[$newsItemId]['image_url_original'] = $originalUrl;
                $resolved[$newsItemId]['image_url_local'] = $localUrl;
                $resolved[$newsItemId]['image_url'] = $localUrl ?? $originalUrl;

                continue;
            }

            $resolved[$newsItemId]['media_original'][] = [
                'url' => $originalUrl,
                'type' => $sourceType,
            ];

            if ($localUrl !== null) {
                $resolved[$newsItemId]['media_local'][] = [
                    'url' => $localUrl,
                    'type' => $effectiveType,
                ];
            }

            $resolved[$newsItemId]['media'][] = [
                'url' => $localUrl ?? $originalUrl,
                'type' => $effectiveType,
            ];
        }

        return $resolved;
    }

    private function resolveLocalUrl(string $diskName, ?string $localPath): ?string
    {
        if ($localPath === null) {
            return null;
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($diskName);
        if (! $disk->exists($localPath)) {
            return null;
        }

        return $disk->url($localPath);
    }

    /**
     * @return array{
     *   image_url:?string,
     *   image_url_original:?string,
     *   image_url_local:?string,
     *   media:list<array{url:string, type:?string}>,
     *   media_original:list<array{url:string, type:?string}>,
     *   media_local:list<array{url:string, type:?string}>
     * }
     */
    private function defaultMediaState(): array
    {
        return [
            'image_url' => null,
            'image_url_original' => null,
            'image_url_local' => null,
            'media' => [],
            'media_original' => [],
            'media_local' => [],
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
