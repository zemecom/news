<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Actions;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use RuntimeException;
use Throwable;

final readonly class PreloadNewsMediaAction
{
    public function __construct(
        private NewsRepository $news,
        private NewsMediaAssetRepository $mediaAssets,
    ) {}

    public function __invoke(int $newsItemId): void
    {
        $urls = $this->news->getMediaUrls($newsItemId);

        if ($urls === null) {
            return;
        }

        $imageUrl = $urls['image_url'];
        $media = $urls['media'];

        $this->mediaAssets->syncOriginalMedia($newsItemId, $imageUrl, $media);
        $candidates = $this->mediaAssets->getDownloadCandidates($newsItemId);

        foreach ($candidates as $candidate) {
            try {
                $this->downloadCandidate($candidate);
            } catch (Throwable $e) {
                $this->mediaAssets->markFailed((int) $candidate['id'], $e->getMessage());

                Log::warning('Failed to preload media asset', [
                    'news_item_id' => $newsItemId,
                    'asset_id' => (int) $candidate['id'],
                    'url' => (string) $candidate['source_url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array{id:int, source_url:string, local_disk:string}  $candidate
     */
    private function downloadCandidate(array $candidate): void
    {
        $response = Http::timeout(15)->retry(2, 200)->get($candidate['source_url']);
        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Download failed with HTTP status %d', $response->status()));
        }

        $body = $response->body();
        if ($body === '') {
            throw new RuntimeException('Downloaded body is empty.');
        }

        $diskName = trim($candidate['local_disk']) !== '' ? $candidate['local_disk'] : 'public';
        $disk = Storage::disk($diskName);
        $mimeType = $this->normalizeMimeType($response->header('Content-Type'));
        $extension = $this->resolveFileExtension($candidate['source_url'], $mimeType);
        $relativePath = 'media/'.date('Y/m/d').'/'.Str::uuid()->toString().'.'.$extension;

        if ($disk->put($relativePath, $body) === false) {
            throw new RuntimeException('Unable to write media file to storage.');
        }

        $this->mediaAssets->markDownloaded(
            assetId: (int) $candidate['id'],
            localDisk: $diskName,
            localPath: $relativePath,
            downloadedMimeType: $mimeType,
            fileSizeBytes: strlen($body),
            checksumSha256: hash('sha256', $body),
        );
    }

    private function resolveFileExtension(string $url, ?string $mimeType): string
    {
        $mimeExtension = $this->extensionByMimeType($mimeType);
        if ($mimeExtension !== null) {
            return $mimeExtension;
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (preg_match('/^[a-z0-9]{1,10}$/i', $ext) === 1) {
                return mb_strtolower($ext);
            }
        }

        return 'bin';
    }

    private function normalizeMimeType(?string $contentTypeHeader): ?string
    {
        if ($contentTypeHeader === null || trim($contentTypeHeader) === '') {
            return null;
        }

        $rawType = trim(explode(';', $contentTypeHeader)[0]);
        if ($rawType === '') {
            return null;
        }

        return mb_strtolower($rawType);
    }

    private function extensionByMimeType(?string $mimeType): ?string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'image/svg+xml' => 'svg',
            'video/mp4' => 'mp4',
            'application/pdf' => 'pdf',
            default => null,
        };
    }
}
