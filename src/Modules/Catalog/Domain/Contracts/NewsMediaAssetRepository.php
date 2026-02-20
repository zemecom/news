<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Contracts;

interface NewsMediaAssetRepository
{
    /**
     * Синхронизирует оригинальные медиа-ссылки статьи в хранилище ассетов.
     *
     * @param  array<int, mixed>  $media
     */
    public function syncOriginalMedia(int $newsItemId, ?string $imageUrl, array $media): void;

    /**
     * Возвращает ассеты, которые нужно скачать локально.
     *
     * @return list<array{id:int, source_url:string, local_disk:string}>
     */
    public function getDownloadCandidates(int $newsItemId): array;

    public function markDownloaded(
        int $assetId,
        string $localDisk,
        string $localPath,
        ?string $downloadedMimeType,
        int $fileSizeBytes,
        string $checksumSha256,
    ): void;

    public function markFailed(int $assetId, string $error): void;

    /**
     * @param  list<int>  $newsItemIds
     * @return array<int, array{
     *   image_url:?string,
     *   image_url_original:?string,
     *   image_url_local:?string,
     *   media:list<array{url:string, type:?string}>,
     *   media_original:list<array{url:string, type:?string}>,
     *   media_local:list<array{url:string, type:?string}>
     * }>
     */
    public function resolveForNewsItems(array $newsItemIds): array;
}
