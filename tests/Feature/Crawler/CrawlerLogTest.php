<?php

declare(strict_types=1);

namespace Tests\Feature\Crawler;

use App\Livewire\CrawlerLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Crawler\Application\Jobs\FetchSourceJob;
use Tests\TestCase;

final class CrawlerLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_source_run_logs_that_crawler_worker_must_pick_up_the_queued_job(): void
    {
        Queue::fake();
        $logFile = storage_path('logs/crawler-run-test.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }

        try {
            $source = Source::query()->create([
                'name' => 'TOPOR Live',
                'url' => '@toporlive',
                'type' => 'telegram',
                'language_default' => 'ru',
                'cron_expression' => '*/2 * * * *',
                'is_active' => true,
            ]);

            Livewire::test(CrawlerLog::class, ['sourceId' => (int) $source->getKey()])
                ->set('logFile', $logFile)
                ->call('startParsing')
                ->assertSet('isStarted', true);

            Queue::assertPushed(
                FetchSourceJob::class,
                fn (FetchSourceJob $job): bool => (int) $job->source['id'] === (int) $source->getKey()
            );

            $this->assertFileExists($logFile);
            $log = (string) file_get_contents($logFile);
            $this->assertStringContainsString('Queued source #'.$source->getKey().' (@toporlive).', $log);
            $this->assertStringContainsString('Waiting for crawler_tasks worker to process the job.', $log);
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }
}
