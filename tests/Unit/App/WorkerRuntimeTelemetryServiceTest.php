<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\WorkerRuntimeTelemetryService;
use Tests\TestCase;

final class WorkerRuntimeTelemetryServiceTest extends TestCase
{
    public function test_it_tracks_heartbeat_processed_job_and_last_failure(): void
    {
        config()->set('cache.default', 'array');
        config()->set('workers.heartbeat_ttl_seconds', 120);

        $service = new WorkerRuntimeTelemetryService('array');

        $service->recordHeartbeat('crawler-worker');
        $service->recordProcessedJob('crawler-worker', 'Modules\\Crawler\\Application\\Jobs\\FetchSourceJob');
        $service->recordFailedJob('crawler-worker', 'Modules\\Crawler\\Application\\Jobs\\FetchSourceJob', 'HTTP 500');

        $snapshot = $service->snapshot('crawler-worker');

        $this->assertNotNull($snapshot['last_heartbeat_at']);
        $this->assertSame('Modules\\Crawler\\Application\\Jobs\\FetchSourceJob', $snapshot['last_processed_job']);
        $this->assertSame('Modules\\Crawler\\Application\\Jobs\\FetchSourceJob', $snapshot['last_failed_job']);
        $this->assertSame('HTTP 500', $snapshot['last_error_summary']);
        $this->assertFalse($snapshot['heartbeat_stale']);
    }

    public function test_it_marks_heartbeat_as_stale_after_ttl(): void
    {
        config()->set('cache.default', 'array');
        config()->set('workers.heartbeat_ttl_seconds', 120);

        $service = new WorkerRuntimeTelemetryService('array');
        $service->putSnapshot('crawler-worker', [
            'last_heartbeat_at' => now()->subSeconds(121)->toIso8601String(),
            'last_processed_job' => null,
            'last_failed_job' => null,
            'last_error_summary' => null,
        ]);

        $snapshot = $service->snapshot('crawler-worker');

        $this->assertTrue($snapshot['heartbeat_stale']);
    }
}
