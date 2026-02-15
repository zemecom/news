<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Crawler\Application\Actions\FeedFetcherAction;

final class NewsCrawlCommand extends Command
{
    protected $signature = 'news:crawl {--source-id= : Crawl only one source id}';

    protected $description = 'Fetch RSS/Atom sources and publish raw messages to RabbitMQ.';

    public function handle(FeedFetcherAction $fetchFeed): int
    {
        $query = Source::query()
            ->where('is_active', true)
            ->where('type', 'rss')
            ->orderBy('id');

        $sourceId = $this->option('source-id');
        if (is_numeric($sourceId)) {
            $query->where('id', (int) $sourceId);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Source> $sources */
        $sources = $query->get(['id', 'url', 'language_default']);
        if ($sources->isEmpty()) {
            $this->warn('No active sources found.');

            return self::SUCCESS;
        }

        /** @var Source $source */
        foreach ($sources as $source) {
            try {
                $sourceId = (int) $source->getAttribute('id');

                ($fetchFeed)([
                    'id' => $sourceId,
                    'url' => (string) $source->getAttribute('url'),
                    'language_default' => $source->getAttribute('language_default'),
                ]);

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
