<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;

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
        $params = [];
        if ($this->sourceId) {
            $params[] = "--source-id={$this->sourceId}";
        }
        if ($this->dateFrom) {
            $params[] = "--date-from='{$this->dateFrom}'";
        }
        if ($this->dateTo) {
            $params[] = "--date-to='{$this->dateTo}'";
        }
        if ($this->limit) {
            $params[] = "--limit={$this->limit}";
        }
        $params[] = '--sync';

        $paramString = implode(' ', $params);
        $artisan = base_path('artisan');

        // Clear previous log
        file_put_contents($this->logFile, "Starting crawler with params: {$paramString}...\n");

        $cmd = "php {$artisan} news:crawl {$paramString} --no-ansi >> {$this->logFile} 2>&1 &";

        // Run in background
        $handle = popen($cmd, 'r');
        if (is_resource($handle)) {
            pclose($handle);
        }

        $this->isStarted = true;
    }

    public function updateLog(): void
    {
        if ($this->isStarted && file_exists($this->logFile)) {
            $this->output = file_get_contents($this->logFile) ?: 'Running...';
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.crawler-log', [
            'isStarted' => $this->isStarted,
        ]);
    }
}
