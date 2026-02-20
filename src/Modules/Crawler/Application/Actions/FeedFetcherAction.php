<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use Illuminate\Support\Facades\Log;
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
        $logger = Log::channel('stderr');
        $logger->info(sprintf('[Fetcher] Starting action for source #%d (%s)', $source['id'], $source['url']));

        try {
            $items = match ($source['type']) {
                'rss' => $this->rssClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
                'telegram' => $this->telegramClient->fetch($source['url'], $dateFrom, $dateTo, $limit),
                default => throw new InvalidArgumentException('Unsupported source type: '.$source['type']),
            };

            $logger->info(sprintf('[Fetcher] Source #%d returned %d raw items. Processing...', $source['id'], $items->count()));

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
                $logger->info(sprintf('[Fetcher] Published new item: %s', $raw->title));
            }

            $logger->info(sprintf('[Fetcher] Source #%d finished. Total: %d, New: %d, Duplicates: %d', $source['id'], $stats['total'], $stats['new'], $stats['duplicates']));

            $this->events->dispatch(new \Modules\Crawler\Domain\Events\SourceFetchSucceeded(
                (int) $source['id'],
                $stats['total']
            ));

            return $stats;
        } catch (Throwable $e) {
            $logger->error(sprintf('[Fetcher] Source #%d failed: %s', $source['id'], $e->getMessage()));

            $this->events->dispatch(new \Modules\Crawler\Domain\Events\SourceFetchFailed(
                (int) $source['id'],
                $e->getMessage()
            ));

            throw $e;
        }
    }
}
