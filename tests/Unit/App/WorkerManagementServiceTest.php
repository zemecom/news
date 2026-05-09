<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\Contracts\QueuePreviewClient;
use App\Services\Contracts\WorkerSupervisor;
use App\Services\QueueManagementService;
use App\Services\QueueOverviewService;
use App\Services\WorkerManagementService;
use App\Services\WorkerRuntimeTelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

final class WorkerManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_worker_snapshots_and_marks_not_configured_runtime_health(): void
    {
        config()->set('workers.runtimes', [
            'worker' => [
                'service' => 'worker',
                'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
            ],
        ]);

        $overviewChannel = $this->createMock(AMQPChannel::class);
        $overviewChannel->method('queue_declare')
            ->willReturnCallback(static fn (string $queue): array => match ($queue) {
                'crawler_tasks' => ['crawler_tasks', 3, 0],
                'intelligence_tasks' => ['intelligence_tasks', 0, 0],
                'media_tasks' => ['media_tasks', 0, 0],
                default => [$queue, 0, 0],
            });
        $overviewChannel->method('close');

        $overviewConnection = $this->createMock(AMQPStreamConnection::class);
        $overviewConnection->method('channel')->willReturn($overviewChannel);

        $managementConnection = $this->createMock(AMQPStreamConnection::class);

        $queueManagement = new QueueManagementService($managementConnection, new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        });

        $telemetry = new WorkerRuntimeTelemetryService('array');

        $supervisor = new class implements WorkerSupervisor
        {
            public function listRuntimes(): array
            {
                return ['worker'];
            }

            public function status(string $runtime): array
            {
                return [
                    'runtime' => $runtime,
                    'service' => 'worker',
                    'state' => 'not_configured',
                    'container_present' => false,
                    'replica_count' => 0,
                    'running_replica_count' => 0,
                    'memory_bytes' => null,
                    'cpu_percent' => null,
                    'uptime_seconds' => null,
                    'restart_count' => null,
                    'started_at' => null,
                    'error' => 'Worker control is not configured.',
                    'operator_commands' => [
                        'start_all' => 'docker compose --profile queue up -d worker',
                    ],
                ];
            }

            public function start(string $runtime): array
            {
                return ['success' => false, 'runtime' => $runtime, 'message' => 'not configured'];
            }

            public function stop(string $runtime): array
            {
                return ['success' => false, 'runtime' => $runtime, 'message' => 'not configured'];
            }

            public function restart(string $runtime): array
            {
                return ['success' => false, 'runtime' => $runtime, 'message' => 'not configured'];
            }

            public function tailLogs(string $runtime, int $lines = 50): array
            {
                return ['runtime' => $runtime, 'lines' => [], 'error' => 'not configured'];
            }

            public function isConfigured(): bool
            {
                return false;
            }

            public function backendLabel(): string
            {
                return 'not-configured';
            }
        };

        $service = new WorkerManagementService(
            overview: new QueueOverviewService($overviewConnection),
            queueManagement: $queueManagement,
            supervisor: $supervisor,
            telemetry: $telemetry,
        );

        $workers = $service->listWorkers();

        $this->assertCount(1, $workers);
        $this->assertSame('worker', $workers[0]['runtime']);
        $this->assertSame('crawler_tasks', $workers[0]['queue']);
        $this->assertSame(['crawler_tasks', 'intelligence_tasks', 'media_tasks'], $workers[0]['queues']);
        $this->assertSame('not_configured', $workers[0]['health']);
        $this->assertSame(3, $workers[0]['message_count']);
    }

    public function test_it_marks_runtime_as_degraded_when_heartbeat_is_stale_and_queue_has_backlog(): void
    {
        config()->set('workers.runtimes', [
            'worker' => [
                'service' => 'worker',
                'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
            ],
        ]);
        config()->set('workers.heartbeat_ttl_seconds', 120);

        $overviewChannel = $this->createMock(AMQPChannel::class);
        $overviewChannel->method('queue_declare')
            ->willReturnCallback(static fn (string $queue): array => match ($queue) {
                'crawler_tasks' => ['crawler_tasks', 7, 0],
                'intelligence_tasks' => ['intelligence_tasks', 0, 0],
                'media_tasks' => ['media_tasks', 0, 0],
                default => [$queue, 0, 0],
            });
        $overviewChannel->method('close');

        $overviewConnection = $this->createMock(AMQPStreamConnection::class);
        $overviewConnection->method('channel')->willReturn($overviewChannel);

        $managementConnection = $this->createMock(AMQPStreamConnection::class);

        $queueManagement = new QueueManagementService($managementConnection, new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        });

        $telemetry = new WorkerRuntimeTelemetryService('array');
        $telemetry->putSnapshot('worker', [
            'last_heartbeat_at' => now()->subSeconds(121)->toIso8601String(),
            'last_processed_job' => null,
            'last_failed_job' => null,
            'last_error_summary' => null,
        ]);

        $supervisor = new class implements WorkerSupervisor
        {
            public function listRuntimes(): array
            {
                return ['worker'];
            }

            public function status(string $runtime): array
            {
                return [
                    'runtime' => $runtime,
                    'service' => 'worker',
                    'state' => 'running',
                    'container_present' => true,
                    'replica_count' => 2,
                    'running_replica_count' => 1,
                    'memory_bytes' => 1000000,
                    'cpu_percent' => 1.2,
                    'uptime_seconds' => 300,
                    'restart_count' => 0,
                    'started_at' => now()->subMinutes(5)->toIso8601String(),
                    'error' => null,
                    'operator_commands' => [],
                ];
            }

            public function start(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'started'];
            }

            public function stop(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'stopped'];
            }

            public function restart(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'restarted'];
            }

            public function tailLogs(string $runtime, int $lines = 50): array
            {
                return ['runtime' => $runtime, 'lines' => [], 'error' => null];
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function backendLabel(): string
            {
                return 'http';
            }
        };

        $service = new WorkerManagementService(
            overview: new QueueOverviewService($overviewConnection),
            queueManagement: $queueManagement,
            supervisor: $supervisor,
            telemetry: $telemetry,
        );

        $workers = $service->listWorkers();

        $this->assertSame('degraded', $workers[0]['health']);
        $this->assertTrue($workers[0]['heartbeat_stale']);
        $this->assertSame(7, $workers[0]['message_count']);
        $this->assertSame(2, $workers[0]['replica_count']);
    }

    public function test_it_soft_restarts_all_laravel_workers_via_queue_restart(): void
    {
        config()->set('workers.runtimes', []);

        $overviewConnection = $this->createMock(AMQPStreamConnection::class);
        $managementConnection = $this->createMock(AMQPStreamConnection::class);

        Artisan::spy();
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:restart')
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        $queueManagement = new QueueManagementService($managementConnection, new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        });

        $supervisor = new class implements WorkerSupervisor
        {
            public function listRuntimes(): array
            {
                return [];
            }

            public function status(string $runtime): array
            {
                return [];
            }

            public function start(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'started'];
            }

            public function stop(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'stopped'];
            }

            public function restart(string $runtime): array
            {
                return ['success' => true, 'runtime' => $runtime, 'message' => 'restarted'];
            }

            public function tailLogs(string $runtime, int $lines = 50): array
            {
                return ['runtime' => $runtime, 'lines' => [], 'error' => null];
            }

            public function isConfigured(): bool
            {
                return true;
            }

            public function backendLabel(): string
            {
                return 'http';
            }
        };

        $service = new WorkerManagementService(
            overview: new QueueOverviewService($overviewConnection),
            queueManagement: $queueManagement,
            supervisor: $supervisor,
            telemetry: new WorkerRuntimeTelemetryService('array'),
        );

        $result = $service->softRestartWorkers();

        $this->assertTrue($result['success']);
        $this->assertStringContainsString('queue:restart', (string) $result['message']);
    }
}
