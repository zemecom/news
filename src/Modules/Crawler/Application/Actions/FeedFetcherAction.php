<?php

declare(strict_types=1);

namespace Modules\Crawler\Application\Actions;

use Carbon\Carbon;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Modules\Crawler\Application\Services\RawNewsFactory;
use Modules\Crawler\Domain\Contracts\Deduplicator;
use Modules\Crawler\Domain\Contracts\RawPublisher;
use Modules\Crawler\Domain\Contracts\RssClient;
use Modules\Crawler\Domain\Contracts\TelegramClient;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Events\SourceFetchFailed;
use Modules\Shared\Domain\Events\SourceFetchSucceeded;
use Throwable;

/**
 * Главный оркестратор модуля Crawler (Слой: Application).
 *
 * Алгоритм работы:
 * 1. Получает сырые данные о канале/ленте (`$source`).
 * 2. Делегирует HTTP-скачивание клиентам (TelegramClient или RssClient) слой Infrastructure.
 * 3. Превращает "сырой" ответ в DTO `RawNewsData` через фабрику.
 * 4. Пакетно проверяет дубликаты через интерфейс `Deduplicator` (сохраняя идемпотентность парсинга).
 * 5. Уникальные посты отправляет в RabbitMQ (через `RawPublisher`) для модуля Intelligence.
 * 6. Выбрасывает доменные события об успехе/ошибке (для обновления статусов в модуле Catalog).
 */
final readonly class FeedFetcherAction
{
    public function __construct(
        private RssClient $rssClient,
        private TelegramClient $telegramClient,
        private RawPublisher $publisher,
        private RawNewsFactory $rawNewsFactory,
        private Deduplicator $deduplicator,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array{id:int,url:string,type:string,language_default:string|null}  $source
     * @return array{total: int, new: int, duplicates: int}
     */
    public function __invoke(array $source, ?Carbon $dateFrom = null, ?Carbon $dateTo = null, ?int $limit = null): array
    {
        $logger = Log::channel('crawler');
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

            /** @var list<RawNewsData> $rawItems */
            $rawItems = [];
            $fingerprints = [];

            foreach ($items as $item) {
                $raw = $this->rawNewsFactory->fromRss($source, $item);
                $rawItems[] = $raw;

                if ($raw->fingerprint !== '') {
                    $fingerprints[$raw->fingerprint] = $raw->fingerprint;
                }
            }

            $existingFingerprints = array_fill_keys(
                $this->deduplicator->existingFingerprints(array_values($fingerprints)),
                true
            );
            $seenFingerprints = [];

            foreach ($rawItems as $raw) {
                if (isset($existingFingerprints[$raw->fingerprint]) || isset($seenFingerprints[$raw->fingerprint])) {
                    $stats['duplicates']++;

                    continue;
                }

                $seenFingerprints[$raw->fingerprint] = true;
                $stats['new']++;
                $this->publisher->publish($raw);
                $logger->info(sprintf('[Fetcher] Published new item: %s', $raw->title));
            }

            $logger->info(sprintf('[Fetcher] Source #%d finished. Total: %d, New: %d, Duplicates: %d', $source['id'], $stats['total'], $stats['new'], $stats['duplicates']));

            $this->events->dispatch(new SourceFetchSucceeded(
                (int) $source['id'],
                $stats['total']
            ));

            return $stats;
        } catch (Throwable $e) {
            $logger->error(sprintf('[Fetcher] Source #%d failed: %s', $source['id'], $e->getMessage()));

            $this->events->dispatch(new SourceFetchFailed(
                (int) $source['id'],
                $e->getMessage()
            ));

            throw $e;
        }
    }
}
