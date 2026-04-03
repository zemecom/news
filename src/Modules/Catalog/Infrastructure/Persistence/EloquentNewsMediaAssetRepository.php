<?php

declare(strict_types=1);

namespace Modules\Catalog\Infrastructure\Persistence;

use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsMediaAsset;

final readonly class EloquentNewsMediaAssetRepository implements NewsMediaAssetRepository
{
    private const string SLOT_COVER = 'cover';

    private const string SLOT_GALLERY = 'gallery';

    private const string STATUS_PENDING = 'pending';

    private const string STATUS_DOWNLOADED = 'downloaded';

    private const string STATUS_FAILED = 'failed';

    public function __construct(private DatabaseManager $db) {}

    public function syncOriginalMedia(int $newsItemId, ?string $imageUrl, array $media): void
    {
        $desiredAssets = $this->buildDesiredAssets($imageUrl, $media);

        $this->db->connection()->transaction(function () use ($newsItemId, $desiredAssets): void {
            /** @var Collection<string, NewsMediaAsset> $existing */
            $existing = NewsMediaAsset::query()
                ->where('news_item_id', $newsItemId)
                ->get()
                ->keyBy(fn (NewsMediaAsset $asset): string => $this->assetKey($asset->slot, (int) $asset->position));

            /** @var array<string, true> $seenKeys */
            $seenKeys = [];

            foreach ($desiredAssets as $assetPayload) {
                $key = $this->assetKey($assetPayload['slot'], $assetPayload['position']);
                $seenKeys[$key] = true;

                /** @var NewsMediaAsset|null $current */
                $current = $existing->get($key);

                if ($current === null) {
                    NewsMediaAsset::query()->create([
                        'news_item_id' => $newsItemId,
                        'slot' => $assetPayload['slot'],
                        'position' => $assetPayload['position'],
                        'source_url' => $assetPayload['source_url'],
                        'source_mime_type' => $assetPayload['source_mime_type'],
                        'local_disk' => 'public',
                        'download_status' => self::STATUS_PENDING,
                    ]);

                    continue;
                }

                $sourceChanged = $current->source_url !== $assetPayload['source_url'];
                $typeChanged = $current->source_mime_type !== $assetPayload['source_mime_type'];

                if (! $sourceChanged && ! $typeChanged) {
                    continue;
                }

                $updates = [
                    'source_url' => $assetPayload['source_url'],
                    'source_mime_type' => $assetPayload['source_mime_type'],
                ];

                if ($sourceChanged) {
                    $updates = array_merge($updates, [
                        'local_path' => null,
                        'downloaded_mime_type' => null,
                        'file_size_bytes' => null,
                        'checksum_sha256' => null,
                        'download_status' => self::STATUS_PENDING,
                        'last_error' => null,
                        'downloaded_at' => null,
                    ]);
                }

                NewsMediaAsset::query()->whereKey($current->id)->update($updates);
            }

            $deleteIds = $existing
                ->reject(fn (NewsMediaAsset $asset, string $key): bool => isset($seenKeys[$key]))
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if ($deleteIds !== []) {
                NewsMediaAsset::query()->whereIn('id', $deleteIds)->delete();
            }
        });
    }

    public function getDownloadCandidates(int $newsItemId): array
    {
        /** @var Collection<int, NewsMediaAsset> $assets */
        $assets = NewsMediaAsset::query()
            ->where('news_item_id', $newsItemId)
            ->whereNotNull('source_url')
            ->whereRaw('LOWER(source_url) LIKE ?', ['http%'])
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('local_path')
                    ->orWhere('download_status', '!=', self::STATUS_DOWNLOADED);
            })
            ->orderBy('slot')
            ->orderBy('position')
            ->get();

        /** @var array<int, array{id:int, source_url:string, local_disk:string}> $candidates */
        $candidates = $assets
            ->map(function (NewsMediaAsset $asset): array {
                $localDisk = trim((string) $asset->local_disk);

                return [
                    'id' => (int) $asset->id,
                    'source_url' => (string) $asset->source_url,
                    'local_disk' => $localDisk !== '' ? $localDisk : 'public',
                ];
            })
            ->all();

        return array_values($candidates);
    }

    public function markDownloaded(
        int $assetId,
        string $localDisk,
        string $localPath,
        ?string $downloadedMimeType,
        int $fileSizeBytes,
        string $checksumSha256,
    ): void {
        NewsMediaAsset::query()->whereKey($assetId)->update([
            'local_disk' => $localDisk !== '' ? $localDisk : 'public',
            'local_path' => $localPath,
            'downloaded_mime_type' => $downloadedMimeType,
            'file_size_bytes' => max(0, $fileSizeBytes),
            'checksum_sha256' => $checksumSha256,
            'download_status' => self::STATUS_DOWNLOADED,
            'last_error' => null,
            'downloaded_at' => now(),
        ]);
    }

    public function markFailed(int $assetId, string $error): void
    {
        NewsMediaAsset::query()->whereKey($assetId)->update([
            'download_status' => self::STATUS_FAILED,
            'last_error' => $error,
        ]);
    }

    public function resolveForNewsItems(array $newsItemIds): array
    {
        if ($newsItemIds === []) {
            return [];
        }

        $resolved = [];
        foreach ($newsItemIds as $newsItemId) {
            $resolved[(int) $newsItemId] = $this->defaultMediaState();
        }

        /** @var Collection<int, NewsMediaAsset> $assets */
        $assets = NewsMediaAsset::query()
            ->whereIn('news_item_id', $newsItemIds)
            ->orderBy('news_item_id')
            ->orderBy('slot')
            ->orderBy('position')
            ->get();

        foreach ($assets as $asset) {
            $newsItemId = (int) $asset->news_item_id;
            if (! array_key_exists($newsItemId, $resolved)) {
                $resolved[$newsItemId] = $this->defaultMediaState();
            }

            $originalUrl = $this->normalizeString($asset->source_url);
            if ($originalUrl === null) {
                continue;
            }

            $localUrl = $this->resolveLocalUrl($asset);
            $sourceType = $this->normalizeString($asset->source_mime_type);
            $effectiveType = $this->normalizeString($asset->downloaded_mime_type) ?? $sourceType;

            if ($asset->slot === self::SLOT_COVER) {
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

    /**
     * @param  array<int, mixed>  $media
     * @return list<array{
     *   slot:string,
     *   position:int,
     *   source_url:string,
     *   source_mime_type:?string
     * }>
     */
    private function buildDesiredAssets(?string $imageUrl, array $media): array
    {
        $desired = [];

        $normalizedImageUrl = $this->normalizeString($imageUrl);
        if ($normalizedImageUrl !== null) {
            $desired[] = [
                'slot' => self::SLOT_COVER,
                'position' => 0,
                'source_url' => $normalizedImageUrl,
                'source_mime_type' => null,
            ];
        }

        $position = 0;
        foreach ($media as $mediaItem) {
            $parsed = $this->parseMediaItem($mediaItem);
            if ($parsed === null) {
                continue;
            }

            $desired[] = [
                'slot' => self::SLOT_GALLERY,
                'position' => $position,
                'source_url' => $parsed['url'],
                'source_mime_type' => $parsed['type'],
            ];
            $position++;
        }

        return $desired;
    }

    private function assetKey(string $slot, int $position): string
    {
        return $slot.':'.$position;
    }

    /**
     * @return array{url:string, type:?string}|null
     */
    private function parseMediaItem(mixed $mediaItem): ?array
    {
        if (is_array($mediaItem)) {
            $url = $this->normalizeString($mediaItem['url'] ?? null);
            if ($url === null) {
                return null;
            }

            return [
                'url' => $url,
                'type' => $this->normalizeString($mediaItem['type'] ?? null),
            ];
        }

        if (is_string($mediaItem)) {
            $url = $this->normalizeString($mediaItem);
            if ($url === null) {
                return null;
            }

            return [
                'url' => $url,
                'type' => null,
            ];
        }

        return null;
    }

    private function resolveLocalUrl(NewsMediaAsset $asset): ?string
    {
        $localPath = $this->normalizeString($asset->local_path);
        if ($localPath === null) {
            return null;
        }

        $diskName = $this->normalizeString($asset->local_disk) ?? 'public';
        /** @var FilesystemAdapter $disk */
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
