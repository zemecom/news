<?php

declare(strict_types=1);

namespace Tests\Unit\Crawler;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Crawler\Application\Actions\FeedFetcherAction;
use Modules\Crawler\Application\Services\IncomingContentSanitizer;
use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\Deduplicator;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient;
use Modules\Shared\Application\Services\FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Events\SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded;
use Tests\TestCase;

final class FeedFetcherActionTest extends TestCase
{
    public function test_fetches_items_from_telegram_source(): void
    {
        Log::shouldReceive('channel')->with('crawler')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $rssClient = $this->createMock(RssClient::class);
        $telegramClient = $this->createMock(TelegramClient::class);
        $publisher = $this->createMock(RawPublisher::class);
        $rawNewsFactory = new RawNewsFactory(new FingerprintGenerator, new IncomingContentSanitizer);

        $source = [
            'id' => 11,
            'url' => '@toporlive',
            'type' => 'telegram',
            'language_default' => 'ru',
        ];

        $item = [
            'guid' => 'toporlive/1',
            'title' => 'Telegram title',
            'link' => 'https://t.me/toporlive/1',
            'description' => 'Post text',
        ];

        $rssClient
            ->expects($this->never())
            ->method('fetch');

        $telegramClient
            ->expects($this->once())
            ->method('fetch')
            ->with('@toporlive')
            ->willReturn(new Collection([$item]));

        $publisher
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (RawNewsData $raw): bool {
                $this->assertSame(11, $raw->sourceId);
                $this->assertSame('toporlive/1', $raw->externalId);
                $this->assertSame('Telegram title', $raw->title);
                $this->assertSame('https://t.me/toporlive/1', $raw->link);
                $this->assertNotSame('', $raw->fingerprint);

                return true;
            }));

        $deduplicator = $this->createMock(Deduplicator::class);
        $deduplicator
            ->expects($this->once())
            ->method('existingFingerprints')
            ->willReturn([]);

        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SourceFetchSucceeded::class));

        $action = new FeedFetcherAction($rssClient, $telegramClient, $publisher, $rawNewsFactory, $deduplicator, $events);
        $action($source);
    }

    public function test_checks_duplicate_fingerprints_in_one_batch(): void
    {
        Log::shouldReceive('channel')->with('crawler')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $rssClient = $this->createMock(RssClient::class);
        $telegramClient = $this->createMock(TelegramClient::class);
        $publisher = $this->createMock(RawPublisher::class);
        $rawNewsFactory = new RawNewsFactory(new FingerprintGenerator, new IncomingContentSanitizer);

        $source = [
            'id' => 11,
            'url' => 'https://example.com/feed.xml',
            'type' => 'rss',
            'language_default' => 'en',
        ];

        $items = new Collection([
            [
                'guid' => 'existing/1',
                'title' => 'Existing title',
                'link' => 'https://example.com/existing',
                'pubDate' => '2026-02-11T10:00:00+00:00',
            ],
            [
                'guid' => 'fresh/1',
                'title' => 'Fresh title',
                'link' => 'https://example.com/fresh',
                'pubDate' => '2026-02-11T10:01:00+00:00',
            ],
            [
                'guid' => 'fresh/1',
                'title' => 'Fresh title duplicate',
                'link' => 'https://example.com/fresh-copy',
                'pubDate' => '2026-02-11T10:02:00+00:00',
            ],
        ]);

        $rssClient
            ->expects($this->once())
            ->method('fetch')
            ->with('https://example.com/feed.xml')
            ->willReturn($items);

        $telegramClient
            ->expects($this->never())
            ->method('fetch');

        $publisher
            ->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (RawNewsData $raw): bool {
                $this->assertSame('fresh/1', $raw->externalId);

                return true;
            }));

        $deduplicator = new RecordingDeduplicator([
            $this->fingerprintForExternalId(11, 'existing/1'),
        ]);

        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SourceFetchSucceeded::class));

        $action = new FeedFetcherAction($rssClient, $telegramClient, $publisher, $rawNewsFactory, $deduplicator, $events);

        $stats = $action($source);

        $this->assertSame(['total' => 3, 'new' => 1, 'duplicates' => 2], $stats);
        $this->assertSame(0, $deduplicator->existsCalls);
        $this->assertSame(1, $deduplicator->existingFingerprintsCalls);
        $this->assertCount(2, $deduplicator->queriedBatches[0] ?? []);
    }

    public function test_throws_exception_for_unsupported_source_type(): void
    {
        Log::shouldReceive('channel')->with('crawler')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $rssClient = $this->createMock(RssClient::class);
        $telegramClient = $this->createMock(TelegramClient::class);
        $publisher = $this->createMock(RawPublisher::class);
        $rawNewsFactory = new RawNewsFactory(new FingerprintGenerator, new IncomingContentSanitizer);

        $rssClient
            ->expects($this->never())
            ->method('fetch');

        $telegramClient
            ->expects($this->never())
            ->method('fetch');

        $publisher
            ->expects($this->never())
            ->method('publish');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported source type: custom');

        $deduplicator = $this->createMock(Deduplicator::class);
        $events = $this->createMock(Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SourceFetchFailed::class));

        $action = new FeedFetcherAction($rssClient, $telegramClient, $publisher, $rawNewsFactory, $deduplicator, $events);
        $action([
            'id' => 1,
            'url' => 'https://example.com/feed.xml',
            'type' => 'custom',
            'language_default' => 'en',
        ]);
    }

    private function fingerprintForExternalId(int $sourceId, string $externalId): string
    {
        return hash('sha256', sprintf(
            'src:%d|ext:%s',
            $sourceId,
            mb_strtolower($externalId)
        ));
    }
}

final class RecordingDeduplicator implements Deduplicator
{
    public int $existsCalls = 0;

    public int $existingFingerprintsCalls = 0;

    /**
     * @var list<list<string>>
     */
    public array $queriedBatches = [];

    /**
     * @param  list<string>  $existingFingerprints
     */
    public function __construct(private readonly array $existingFingerprints) {}

    public function exists(string $fingerprint): bool
    {
        $this->existsCalls++;

        return in_array($fingerprint, $this->existingFingerprints, true);
    }

    /**
     * @param  list<string>  $fingerprints
     * @return list<string>
     */
    public function existingFingerprints(array $fingerprints): array
    {
        $this->existingFingerprintsCalls++;
        $this->queriedBatches[] = $fingerprints;

        return array_values(array_intersect($fingerprints, $this->existingFingerprints));
    }
}
