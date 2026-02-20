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
use Modules\Catalog\Domain\Contracts\NewsRepository;
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
        public readonly string $newsItemId,
    ) {
        $this->onQueue('media_tasks');
    }

    public function handle(NewsRepository $repository): void
    {
        $urls = $repository->getMediaUrls((int) $this->newsItemId);

        if (! $urls) {
            return;
        }

        $disk = Storage::disk('public');
        $changed = false;

        $imageUrl = $urls['image_url'];
        /** @var array<int|string, mixed> $mediaArray */
        $mediaArray = $urls['media'] ?? [];

        // Обработка главного изображения
        if ($imageUrl && str_starts_with($imageUrl, 'http')) {
            $localPath = $this->downloadMedia($imageUrl, $disk);
            if ($localPath) {
                $imageUrl = Storage::url($localPath);
                $changed = true;
            }
        }

        // Обработка массива media
        foreach ($mediaArray as $index => $mediaItem) {
            if (is_array($mediaItem) && isset($mediaItem['url']) && is_string($mediaItem['url'])) {
                if (str_starts_with($mediaItem['url'], 'http')) {
                    $localPath = $this->downloadMedia($mediaItem['url'], $disk);
                    if ($localPath) {
                        $mediaArray[$index]['url'] = Storage::url($localPath);
                        $changed = true;
                    }
                }
            } elseif (is_string($mediaItem)) {
                if (str_starts_with($mediaItem, 'http')) {
                    $localPath = $this->downloadMedia($mediaItem, $disk);
                    if ($localPath) {
                        $mediaArray[$index] = Storage::url($localPath);
                        $changed = true;
                    }
                }
            }
        }

        if ($changed) {
            $repository->updateMedia((int) $this->newsItemId, $imageUrl, $mediaArray);
        }
    }

    private function downloadMedia(string $url, \Illuminate\Contracts\Filesystem\Filesystem $disk): ?string
    {
        try {
            $response = Http::timeout(10)->get($url);

            if ($response->successful()) {
                $extension = $this->getExtensionFromUrl($url) ?? 'jpg';
                $filename = 'media/'.date('Y/m/d').'/'.Str::uuid()->toString().'.'.$extension;

                $disk->put($filename, $response->body());

                return $filename;
            }
        } catch (Throwable $e) {
            Log::warning('Failed to download media', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function getExtensionFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! $path) {
            return null;
        }

        $ext = pathinfo($path, PATHINFO_EXTENSION);

        return $ext ? mb_strtolower($ext) : null;
    }
}
