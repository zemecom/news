<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Crawler\Application\Actions\FeedFetcherAction;

final class NewsCrawlCommand extends Command
{
    protected $signature = 'news:crawl 
                            {--source-id= : Crawl only one source id}
                            {--date-from= : Parse articles from this date (Y-m-d H:i:s)}
                            {--date-to= : Parse articles until this date (Y-m-d H:i:s)}
                            {--limit= : Maximum number of articles to parse per source}';

    protected $description = 'Fetch active sources and publish raw messages to RabbitMQ.';

    public function handle(FeedFetcherAction $fetchFeed): int
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

        $dateFrom = $this->option('date-from') ? \Carbon\Carbon::parse($this->option('date-from')) : null;
        $dateTo = $this->option('date-to') ? \Carbon\Carbon::parse($this->option('date-to')) : null;
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        /** @var Source $source */
        foreach ($sources as $source) {
            try {
                $sourceId = (int) $source->getAttribute('id');

                ($fetchFeed)(
                    source: [
                        'id' => $sourceId,
                        'url' => (string) $source->getAttribute('url'),
                        'type' => (string) $source->getAttribute('type'),
                        'language_default' => $source->getAttribute('language_default'),
                    ],
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    limit: $limit
                );

                $this->line(sprintf('Fetched source #%d.', $sourceId));
            } catch (\Throwable $e) {
                $this->error(sprintf(
                    'Failed source #%d: %s',
                    (int) $source->getAttribute('id'),
                    $e->getMessage()
                ));
            }
        }

        return self::SUCCESS;
    }
}
