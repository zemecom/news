<?php

declare(strict_types=1);

namespace Tests\Unit\Crawler;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Crawler\Application\Actions\FeedFetcherAction;
use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient;
use Modules\Shared\Application\Services\FingerprintGenerator;
use Modules\Shared\Domain\DTO\RawNewsData;
use Tests\TestCase;

final class FeedFetcherActionTest extends TestCase
{
    public function test_fetches_items_from_telegram_source(): void
    {
        Log::shouldReceive('channel')->with('stderr')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $rssClient = $this->createMock(RssClient::class);
        $telegramClient = $this->createMock(TelegramClient::class);
        $publisher = $this->createMock(RawPublisher::class);
        $rawNewsFactory = new RawNewsFactory(new FingerprintGenerator);

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

        $deduplicator = $this->createMock(\Modules\Crawler\Domain\Contracts\Deduplicator::class);
        $deduplicator->method('exists')->willReturn(false);

        $events = $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Modules\Shared\Domain\Events\SourceFetchSucceeded::class));

        $action = new FeedFetcherAction($rssClient, $telegramClient, $publisher, $rawNewsFactory, $deduplicator, $events);
        $action($source);
    }

    public function test_throws_exception_for_unsupported_source_type(): void
    {
        Log::shouldReceive('channel')->with('stderr')->andReturnSelf();
        Log::shouldReceive('info')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $rssClient = $this->createMock(RssClient::class);
        $telegramClient = $this->createMock(TelegramClient::class);
        $publisher = $this->createMock(RawPublisher::class);
        $rawNewsFactory = new RawNewsFactory(new FingerprintGenerator);

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

        $deduplicator = $this->createMock(\Modules\Crawler\Domain\Contracts\Deduplicator::class);
        $events = $this->createMock(\Illuminate\Contracts\Events\Dispatcher::class);
        $events->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\Modules\Shared\Domain\Events\SourceFetchFailed::class));

        $action = new FeedFetcherAction($rssClient, $telegramClient, $publisher, $rawNewsFactory, $deduplicator, $events);
        $action([
            'id' => 1,
            'url' => 'https://example.com/feed.xml',
            'type' => 'custom',
            'language_default' => 'en',
        ]);
    }
}
