<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use InvalidArgumentException;
use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient;
use Throwable;

final readonly class FeedFetcherAction
{
    public function __construct(
        private RssClient $rssClient,
        private TelegramClient $telegramClient,
        private RawPublisher $publisher,
        private RawNewsFactory $rawNewsFactory,
        private \Modules\Crawler\Domain\Contracts\Deduplicator $deduplicator,
        private \Illuminate\Contracts\Events\Dispatcher $events,
    ) {}

    /**
     * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
     * @return array{total: int, new: int, duplicates: int}
     */
    public function __invoke(array $source, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): array
    {
        try {
            $items = match ($source['type']) {
                'rss' => $this->rssClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
                'telegram' => $this->telegramClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
                default => throw new InvalidArgumentException('Unsupported source type: '.$source['type']),
            };

            $stats = [
                'total' => $items->count(),
                'new' => 0,
                'duplicates' => 0,
            ];

            foreach ($items as $item) {
                $raw = $this->rawNewsFactory->fromRss($source, $item);

                if ($this->deduplicator->exists($raw->fingerprint)) {
                    $stats['duplicates']++;

                    continue;
                }

                $stats['new']++;
                $this->publisher->publish($raw);
            }

            $this->events->dispatch(new \Modules\Crawler\Domain\Events\SourceFetchSucceeded(
                (int) $source['id'],
                $stats['total']
            ));

            return $stats;
        } catch (Throwable $e) {
            $this->events->dispatch(new \Modules\Crawler\Domain\Events\SourceFetchFailed(
                (int) $source['id'],
                $e->getMessage()
            ));

            throw $e;
        }
    }
}
