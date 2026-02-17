<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient;

final readonly class FeedFetcherAction
{
    public function __construct(
        private RssClient $rssClient,
        private TelegramClient $telegramClient,
        private RawPublisher $publisher,
        private RawNewsFactory $rawNewsFactory,
        private \Modules\Crawler\Domain\Contracts\Deduplicator $deduplicator,
    ) {}

    /**
     * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
     */
    public function __invoke(array $source, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): void
    {
        $items = match ($source['type']) {
            'rss' => $this->rssClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
            'telegram' => $this->telegramClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
            default => throw new \InvalidArgumentException('Unsupported source type: '.$source['type']),
        };

        $items->each(function (array $item) use ($source): void {
            $raw = $this->rawNewsFactory->fromRss($source, $item);

            if ($this->deduplicator->exists($raw->fingerprint)) {
                return;
            }

            $this->publisher->publish($raw);
        });
    }
}
