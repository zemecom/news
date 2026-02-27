<?php

declare(strict_types=1);

namespace App\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Crawler\Application\Jobs\FetchSourceJob;
use Modules\Shared\Application\Services\SourceRuntimeHealthPolicy;
use Throwable;

class CrawlerLog extends Component
{
    public string $output = 'Starting...';

    public ?int $sourceId = null;

    public string $logFile = '';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?int $limit = null;

    public bool $isStarted = false;

    public function mount(?int $sourceId = null): void
    {
        $this->sourceId = $sourceId;
        $this->logFile = storage_path('logs/crawler-run.log');
        $this->dateTo = now()->format('Y-m-d\TH:i');
    }

    public function startParsing(): void
    {
        $this->isStarted = false;
        $this->writeLog('Starting crawler...');

        try {
            $dateFrom = $this->parseDate($this->dateFrom);
            $dateTo = $this->parseDate($this->dateTo);
            $limit = is_int($this->limit) && $this->limit > 0 ? $this->limit : null;

            if ($dateFrom instanceof \Carbon\Carbon && $dateTo instanceof \Carbon\Carbon && $dateFrom->gt($dateTo)) {
                [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
                $this->appendLog('date-from is greater than date-to; values were swapped automatically.');
            }

            if ($this->sourceId !== null) {
                $source = Source::query()
                    ->whereKey($this->sourceId)
                    ->first([
                        'id',
                        'url',
                        'type',
                        'language_default',
                        'last_error_at',
                        'error_streak',
                        'retry_backoff_state',
                    ]);

                if ($source === null) {
                    $this->appendLog(sprintf('Source #%d not found.', $this->sourceId));

                    return;
                }

                /** @var SourceRuntimeHealthPolicy $runtimeHealthPolicy */
                $runtimeHealthPolicy = app(SourceRuntimeHealthPolicy::class);
                $nextRetryAt = $runtimeHealthPolicy->resolveNextRetryAt(
                    retryBackoffState: is_array($source->getAttribute('retry_backoff_state')) ? $source->getAttribute('retry_backoff_state') : null,
                    errorStreak: (int) $source->getAttribute('error_streak'),
                    lastErrorAt: $source->getAttribute('last_error_at'),
                );

                if ($runtimeHealthPolicy->isInBackoffWindow($nextRetryAt)) {
                    $this->appendLog(sprintf(
                        'Source #%d is in temporary backoff until %s (error_streak=%d).',
                        (int) $source->getAttribute('id'),
                        $nextRetryAt?->setTimezone((string) config('app.timezone', 'UTC'))->toDateTimeString() ?? 'n/a',
                        (int) $source->getAttribute('error_streak'),
                    ));

                    return;
                }

                $sourceId = (int) $source->getAttribute('id');
                $sourceUrl = (string) $source->getAttribute('url');
                $sourceType = (string) $source->getAttribute('type');
                $sourceLanguage = $source->getAttribute('language_default');

                FetchSourceJob::dispatch(
                    source: [
                        'id' => $sourceId,
                        'url' => $sourceUrl,
                        'type' => $sourceType,
                        'language_default' => $sourceLanguage,
                    ],
                    dateFrom: $dateFrom,
                    dateTo: $dateTo,
                    limit: $limit,
                );

                $this->appendLog(sprintf(
                    'Queued source #%d (%s).',
                    $sourceId,
                    $sourceUrl,
                ));
            } else {
                $options = ['--no-ansi' => true];
                if ($this->dateFrom !== null && trim($this->dateFrom) !== '') {
                    $options['--date-from'] = $dateFrom?->toDateTimeString();
                }
                if ($this->dateTo !== null && trim($this->dateTo) !== '') {
                    $options['--date-to'] = $dateTo?->toDateTimeString();
                }
                if ($limit !== null) {
                    $options['--limit'] = $limit;
                }

                $exitCode = Artisan::call('news:crawl', $options);
                $commandOutput = trim((string) Artisan::output());

                if ($commandOutput !== '') {
                    $this->appendLog($commandOutput);
                }

                $this->appendLog($exitCode === 0 ? 'Crawl command finished.' : 'Crawl command failed.');
            }

            $this->isStarted = true;
        } catch (Throwable $e) {
            $this->appendLog('Failed to start crawler: '.$e->getMessage());
        }
    }

    public function updateLog(): void
    {
        if ($this->isStarted && file_exists($this->logFile)) {
            $this->output = file_get_contents($this->logFile) ?: 'Running...';
        }
    }

    private function writeLog(string $line): void
    {
        file_put_contents($this->logFile, $line.PHP_EOL);
    }

    private function appendLog(string $line): void
    {
        file_put_contents($this->logFile, $line.PHP_EOL, FILE_APPEND);
    }

    private function parseDate(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.crawler-log', [
            'isStarted' => $this->isStarted,
        ]);
    }
}
