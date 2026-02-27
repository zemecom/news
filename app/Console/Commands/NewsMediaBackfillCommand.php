<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Modules\Catalog\Application\Actions\PreloadNewsMediaAction;
use Modules\Catalog\Application\Jobs\PreloadNewsMediaJob;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Throwable;

final class NewsMediaBackfillCommand extends Command
{
    protected $signature = 'news:media:backfill
                            {--from-id= : Include news_items with id >= this value}
                            {--to-id= : Include news_items with id <= this value}
                            {--limit= : Maximum number of eligible items to process}
                            {--chunk=200 : Number of rows to scan per chunk}
                            {--queue=media_tasks : Queue name for async mode}
                            {--all : Reprocess items even if media assets already exist}
                            {--sync : Process synchronously in current process}
                            {--dry-run : Print counters without dispatching or downloading}';

    protected $description = 'Backfill media assets for existing news items (original + local copies).';

    public function handle(PreloadNewsMediaAction $preloadNewsMedia): int
    {
        try {
            $chunkSizeOption = $this->intOption('chunk', min: 1, default: 200);
            $fromId = $this->intOption('from-id', min: 1);
            $toId = $this->intOption('to-id', min: 1);
            $limit = $this->intOption('limit', min: 1);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $chunkSize = $chunkSizeOption ?? 200;

        if ($fromId !== null && $toId !== null && $fromId > $toId) {
            [$fromId, $toId] = [$toId, $fromId];
            $this->warn('Опции --from-id и --to-id были в обратном порядке, значения автоматически переставлены.');
        }

        $processSynchronously = (bool) $this->option('sync');
        $dryRun = (bool) $this->option('dry-run');
        $reprocessAll = (bool) $this->option('all');
        $queueNameOption = $this->option('queue');
        $queueName = is_string($queueNameOption) && trim($queueNameOption) !== ''
            ? trim($queueNameOption)
            : 'media_tasks';

        /** @var Builder<NewsItem> $query */
        $query = NewsItem::query()
            ->select(['id', 'image_url', 'media'])
            ->orderBy('id');

        if ($fromId !== null) {
            $query->where('id', '>=', $fromId);
        }

        if ($toId !== null) {
            $query->where('id', '<=', $toId);
        }

        if (! $reprocessAll) {
            $query->whereNotExists(function (QueryBuilder $subquery): void {
                $subquery
                    ->selectRaw('1')
                    ->from('news_media_assets as assets')
                    ->whereColumn('assets.news_item_id', 'news_items.id');
            });
        }

        $this->line(sprintf(
            'Старт backfill: mode=%s, dry-run=%s, all=%s, chunk=%d%s',
            $processSynchronously ? 'sync' : 'queue',
            $dryRun ? 'yes' : 'no',
            $reprocessAll ? 'yes' : 'no',
            $chunkSize,
            $limit !== null ? ', limit='.$limit : ''
        ));

        $scanCount = 0;
        $eligibleCount = 0;
        $processedCount = 0;
        $queuedCount = 0;
        $failedCount = 0;
        $startedAt = microtime(true);
        $stopRequested = false;

        $query->chunkById($chunkSize, function (Collection $items) use (
            $dryRun,
            $limit,
            $processSynchronously,
            $preloadNewsMedia,
            $queueName,
            &$scanCount,
            &$eligibleCount,
            &$processedCount,
            &$queuedCount,
            &$failedCount,
            &$stopRequested
        ): bool {
            /** @var Collection<int, NewsItem> $items */
            foreach ($items as $item) {
                if ($stopRequested) {
                    break;
                }

                $scanCount++;

                if (! $this->hasBackfillableMedia($item)) {
                    continue;
                }

                if ($limit !== null && $eligibleCount >= $limit) {
                    $stopRequested = true;
                    break;
                }

                $eligibleCount++;

                if ($dryRun) {
                    continue;
                }

                if ($processSynchronously) {
                    try {
                        $preloadNewsMedia((int) $item->id);
                        $processedCount++;
                    } catch (Throwable $e) {
                        $failedCount++;
                        $this->error(sprintf(
                            'sync failed for news_item_id=%d: %s',
                            (int) $item->id,
                            $e->getMessage()
                        ));
                    }

                    continue;
                }

                try {
                    dispatch(new PreloadNewsMediaJob((int) $item->id)->onQueue($queueName));
                    $queuedCount++;
                } catch (Throwable $e) {
                    $failedCount++;
                    $this->error(sprintf(
                        'queue dispatch failed for news_item_id=%d: %s',
                        (int) $item->id,
                        $e->getMessage()
                    ));
                }
            }

            return ! $stopRequested;
        });

        $durationSec = microtime(true) - $startedAt;

        $this->newLine();
        $this->info('Backfill completed.');
        $this->line(sprintf('Scanned rows: %d', $scanCount));
        $this->line(sprintf('Eligible items: %d', $eligibleCount));

        if (! $dryRun) {
            $this->line(sprintf('Queued jobs: %d', $queuedCount));
            $this->line(sprintf('Processed sync: %d', $processedCount));
            $this->line(sprintf('Failed: %d', $failedCount));
        }

        $this->line(sprintf('Duration: %.2f sec', $durationSec));

        if ($dryRun) {
            $this->comment('Dry-run mode: данные не изменялись.');
        }

        if (! $dryRun && ! $processSynchronously && $queuedCount > 0) {
            $this->comment(sprintf(
                'Для выполнения очереди запусти: php artisan queue:work --queue=%s --tries=3',
                $queueName
            ));
        }

        return $failedCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function intOption(string $name, int $min, ?int $default = null): ?int
    {
        $raw = $this->option($name);
        if ($raw === null || $raw === '') {
            return $default;
        }

        if (! is_numeric($raw)) {
            throw new InvalidArgumentException(sprintf('Опция --%s должна быть числом.', $name));
        }

        $value = (int) $raw;
        if ($value < $min) {
            throw new InvalidArgumentException(sprintf('Опция --%s должна быть >= %d.', $name, $min));
        }

        return $value;
    }

    private function hasBackfillableMedia(NewsItem $item): bool
    {
        if ($this->normalizeString($item->image_url) !== null) {
            return true;
        }

        /** @var array<int, mixed>|null $media */
        $media = $item->media;
        if (! is_array($media) || $media === []) {
            return false;
        }

        return array_any($media, fn ($mediaItem) => $this->extractMediaUrl($mediaItem) !== null);
    }

    private function extractMediaUrl(mixed $mediaItem): ?string
    {
        if (is_array($mediaItem)) {
            return $this->normalizeString($mediaItem['url'] ?? null);
        }

        if (is_string($mediaItem)) {
            return $this->normalizeString($mediaItem);
        }

        return null;
    }

    private function normalizeString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }
}
