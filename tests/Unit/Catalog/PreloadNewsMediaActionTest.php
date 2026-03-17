<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Modules\Catalog\Application\Actions\PreloadNewsMediaAction;
use Modules\Catalog\Domain\Contracts\NewsMediaAssetRepository;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Tests\TestCase;

final class PreloadNewsMediaActionTest extends TestCase
{
    public function test_it_downloads_media_and_marks_the_asset_as_downloaded(): void
    {
        Http::fake([
            'https://example.com/cover.jpg' => Http::response('image-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);
        Storage::fake('public');
        Log::shouldReceive('warning')->never();

        $downloadedPath = null;
        $newsRepository = $this->createMock(NewsRepository::class);
        $mediaRepository = $this->createMock(NewsMediaAssetRepository::class);

        $newsRepository->expects($this->once())
            ->method('getMediaUrls')
            ->with(15)
            ->willReturn([
                'image_url' => 'https://example.com/cover.jpg',
                'media' => [
                    ['url' => 'https://example.com/inline.png', 'type' => 'image/png'],
                ],
            ]);
        $mediaRepository->expects($this->once())
            ->method('syncOriginalMedia')
            ->with(15, 'https://example.com/cover.jpg', [
                ['url' => 'https://example.com/inline.png', 'type' => 'image/png'],
            ]);
        $mediaRepository->expects($this->once())
            ->method('getDownloadCandidates')
            ->with(15)
            ->willReturn([
                [
                    'id' => 99,
                    'source_url' => 'https://example.com/cover.jpg',
                    'local_disk' => 'public',
                ],
            ]);
        $mediaRepository->expects($this->once())
            ->method('markDownloaded')
            ->with(
                99,
                'public',
                $this->callback(function (string $localPath) use (&$downloadedPath): bool {
                    $downloadedPath = $localPath;

                    return str_starts_with($localPath, 'media/');
                }),
                'image/jpeg',
                11,
                hash('sha256', 'image-bytes'),
            );

        $action = new PreloadNewsMediaAction($newsRepository, $mediaRepository);
        $action(15);

        $this->assertNotNull($downloadedPath);
        $this->assertTrue(Storage::disk('public')->exists($downloadedPath));
        $this->assertSame('image-bytes', Storage::disk('public')->get($downloadedPath));
    }

    public function test_it_marks_asset_as_failed_when_download_fails(): void
    {
        Http::fake([
            'https://example.com/fail.jpg' => Http::response('', 500),
        ]);
        Storage::fake('public');
        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Failed to preload media asset',
                $this->callback(static function (array $context): bool {
                    return ($context['news_item_id'] ?? null) === 15
                        && ($context['asset_id'] ?? null) === 99
                        && ($context['error'] ?? null) === 'HTTP request returned status code 500';
                }),
            );

        $newsRepository = $this->createMock(NewsRepository::class);
        $mediaRepository = $this->createMock(NewsMediaAssetRepository::class);

        $newsRepository->expects($this->once())
            ->method('getMediaUrls')
            ->with(15)
            ->willReturn([
                'image_url' => 'https://example.com/fail.jpg',
                'media' => [],
            ]);
        $mediaRepository->expects($this->once())
            ->method('syncOriginalMedia')
            ->with(15, 'https://example.com/fail.jpg', []);
        $mediaRepository->expects($this->once())
            ->method('getDownloadCandidates')
            ->with(15)
            ->willReturn([
                [
                    'id' => 99,
                    'source_url' => 'https://example.com/fail.jpg',
                    'local_disk' => 'public',
                ],
            ]);
        $mediaRepository->expects($this->once())
            ->method('markFailed')
            ->with(99, 'HTTP request returned status code 500');

        $action = new PreloadNewsMediaAction($newsRepository, $mediaRepository);
        $action(15);
    }
}
