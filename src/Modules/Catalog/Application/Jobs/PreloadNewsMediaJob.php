<?php

declare(strict_types=1);

namespace Modules\Catalog\Application\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use RuntimeException;
use Throwable;

final class PreloadNewsMediaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly int $newsItemId,
    ) {
        $this->onQueue('media_tasks');
    }

    public function handle(NewsRepository $news, NewsMediaAssetRepository $mediaAssets): void
    {
        $urls = $news->getMediaUrls($this->newsItemId);

        if ($urls === null) {
            return;
        }

        $imageUrl = $urls['image_url'];
        $media = $urls['media'];

        $mediaAssets->syncOriginalMedia($this->newsItemId, $imageUrl, $media);
        $candidates = $mediaAssets->getDownloadCandidates($this->newsItemId);

        foreach ($candidates as $candidate) {
            try {
                $this->downloadCandidate($candidate, $mediaAssets);
            } catch (Throwable $e) {
                $mediaAssets->markFailed((int) $candidate['id'], $e->getMessage());

                Log::warning('Failed to preload media asset', [
                    'news_item_id' => $this->newsItemId,
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
    private function downloadCandidate(array $candidate, NewsMediaAssetRepository $mediaAssets): void
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

        $mediaAssets->markDownloaded(
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
