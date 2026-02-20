<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Throwable;

final class NewsCrawlCommand extends Command
{
    protected $signature = 'news:crawl 
                            {--source-id= : Crawl only one source id}
                            {--date-from= : Parse articles from this date (Y-m-d H:i:s)}
                            {--date-to= : Parse articles until this date (Y-m-d H:i:s)}
                            {--limit= : Maximum number of articles to parse per source}
                            {--sync : Run synchronously without queue}';

    protected $description = 'Fetch active sources and publish raw messages to RabbitMQ.';

    public function handle(): int
    {
        $query = Source::query()
            ->where('is_active', true)
            ->orderBy('id');

        $sourceId = $this->option('source-id');
        if (is_numeric($sourceId)) {
            $query->where('id', (int) $sourceId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Source> $sources */
        $sources = $query->get(['id', 'url', 'type', 'language_default']);
        if ($sources->isEmpty()) {
            $this->warn('No active sources found.');

            return self::SUCCESS;
        }

        $dateFromOption = $this->option('date-from');
        $dateFrom = is_string($dateFromOption) ? \Carbon\Carbon::parse($dateFromOption) : null;

        $dateToOption = $this->option('date-to');
        $dateTo = is_string($dateToOption) ? \Carbon\Carbon::parse($dateToOption) : null;

        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : null;

        // Validation: Swap dates if from > to
        if ($dateFrom !== null && $dateTo !== null && $dateFrom->gt($dateTo)) {
            $temp = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $temp;
            $this->warn('Warning: date-from is greater than date-to. I swapped them automatically.');
        }

        // Mode Selection
        $isDateMode = $dateFrom !== null || $dateTo !== null;

        if ($isDateMode) {
            // In date mode, we ignore the user's limit and use a hard safety limit
            $limit = 10000;
        } else {
            // In limit mode, if limit is null, set a safe default
            $limit ??= 50;
        }

        $isSync = (bool) $this->option('sync');

        if ($isSync) {
            /** @var \Modules\Crawler\Application\Actions\FeedFetcherAction $fetcher */
            $fetcher = app(\Modules\Crawler\Application\Actions\FeedFetcherAction::class);
        }

        /** @var Source $source */
        foreach ($sources as $source) {
            $sourceId = (int) $source->getAttribute('id');
            $sourceUrl = (string) $source->getAttribute('url');
            try {
                if ($isSync) {
                    $this->info(sprintf('Sync-fetching source #%d (%s)...', $sourceId, $sourceUrl));
                    $effectiveDateFrom = $dateFrom;
                    if ($effectiveDateFrom === null) {
                        $latestItem = NewsItem::query()
                            ->where('source_id', $sourceId)
                            ->latest('published_at')
                            ->first(['published_at']);

                        if ($latestItem) {
                            $effectiveDateFrom = $latestItem->getAttribute('published_at');
                            $this->line(sprintf('  Using auto-detected dateFrom: %s', $effectiveDateFrom->toDateTimeString()));
                        }
                    }

                    $stats = $fetcher(
                        source: [
                            'id' => $sourceId,
                            'url' => $sourceUrl,
                            'type' => (string) $source->getAttribute('type'),
                            'language_default' => $source->getAttribute('language_default'),
                        ],
                        dateFrom: $effectiveDateFrom,
                        dateTo: $dateTo,
                        limit: $limit
                    );

                    $this->info(sprintf(
                        'Successfully processed source #%d. Found %d items. (New: %d, Duplicates: %d)',
                        $sourceId,
                        $stats['total'],
                        $stats['new'],
                        $stats['duplicates']
                    ));
                } else {
                    $effectiveDateFrom = $dateFrom;
                    if ($effectiveDateFrom === null) {
                        $latestItem = NewsItem::query()
                            ->where('source_id', $sourceId)
                            ->latest('published_at')
                            ->first(['published_at']);

                        if ($latestItem) {
                            $effectiveDateFrom = $latestItem->getAttribute('published_at');
                        }
                    }

                    \Modules\Crawler\Application\Jobs\FetchSourceJob::dispatch(
                        source: [
                            'id' => $sourceId,
                            'url' => $sourceUrl,
                            'type' => (string) $source->getAttribute('type'),
                            'language_default' => $source->getAttribute('language_default'),
                        ],
                        dateFrom: $effectiveDateFrom,
                        dateTo: $dateTo,
                        limit: $limit
                    );

                    $this->line(sprintf('Queued source #%d.', $sourceId));
                }
            } catch (Throwable $e) {
                $this->error(sprintf(
                    'Failed source #%d: %s',
                    $sourceId,
                    $e->getMessage()
                ));
            }
        }

        $this->info(sprintf('Finished. Processed %d sources.', $sources->count()));

        return self::SUCCESS;
    }
}
