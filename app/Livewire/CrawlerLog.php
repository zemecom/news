<?php

namespace App\Livewire;

use Livewire\Component;

class CrawlerLog extends Component
{
    public string $output = 'Starting...';

    public ?int $sourceId = null;

    public string $logFile = '';

    public function mount(?int $sourceId = null): void
    {
        $this->sourceId = $sourceId;
        $this->logFile = storage_path('logs/crawler-run.log');

        // Clear previous log
        file_put_contents($this->logFile, "Starting crawler...\n");

        $params = $sourceId ? "--source-id={$sourceId}" : '';
        $cmd = "php artisan news:crawl {$params} > {$this->logFile} 2>&1 &";

        // Run in background
        pclose(popen($cmd, 'r'));
    }

    public function updateLog(): void
    {
        if (file_exists($this->logFile)) {
            $this->output = file_get_contents($this->logFile) ?: 'Running...';
        }
    }

    public function render()
    {
        return view('livewire.crawler-log');
    }
}
