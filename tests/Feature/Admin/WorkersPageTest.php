<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\Workers;
use App\Models\User;
use App\Services\WorkerManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WorkersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_workers_page_and_see_runtime_diagnostics(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->app->instance(WorkerManagementService::class, new class
        {
            /**
             * @return list<array<string, mixed>>
             */
            public function listWorkers(): array
            {
                return [
                    [
                        'runtime' => 'worker',
                        'queue' => 'crawler_tasks',
                        'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
                        'queue_summaries' => [
                            [
                                'queue' => 'crawler_tasks',
                                'status' => 'ok',
                                'message_count' => 3,
                                'consumer_count' => 0,
                                'failed_count' => 1,
                                'last_failed_at' => '2026-05-08 13:00:00',
                                'error' => null,
                            ],
                            [
                                'queue' => 'intelligence_tasks',
                                'status' => 'ok',
                                'message_count' => 0,
                                'consumer_count' => 0,
                                'failed_count' => 0,
                                'last_failed_at' => null,
                                'error' => null,
                            ],
                            [
                                'queue' => 'media_tasks',
                                'status' => 'ok',
                                'message_count' => 0,
                                'consumer_count' => 0,
                                'failed_count' => 0,
                                'last_failed_at' => null,
                                'error' => null,
                            ],
                        ],
                        'health' => 'not_configured',
                        'message_count' => 3,
                        'consumer_count' => 0,
                        'failed_count' => 1,
                        'last_failed_at' => '2026-05-08 13:00:00',
                        'memory_bytes' => null,
                        'memory_human' => 'n/a',
                        'cpu_percent' => null,
                        'replica_count' => 0,
                        'running_replica_count' => 0,
                        'uptime_human' => 'n/a',
                        'restart_count' => null,
                        'last_heartbeat_at' => null,
                        'heartbeat_stale' => false,
                        'last_processed_job' => null,
                        'last_failed_job' => null,
                        'last_error_summary' => 'Worker control is not configured.',
                        'operator_commands' => [
                            'start_all' => 'docker compose --profile queue up -d worker',
                            'restart_runtime' => 'docker compose restart worker',
                            'scale_runtime_hint' => 'docker compose --profile queue up -d --scale worker=2 worker',
                        ],
                    ],
                ];
            }

            /**
             * @return array{configured: bool, backend: string, message: ?string}
             */
            public function dockerControlStatus(): array
            {
                return [
                    'configured' => false,
                    'backend' => 'not-configured',
                    'message' => 'Docker control is not configured in app container.',
                ];
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function recentFailedJobs(): array
            {
                return [
                    [
                        'queue' => 'crawler_tasks',
                        'display_name' => 'FetchSourceJob',
                        'exception_summary' => 'HTTP 500',
                        'failed_at' => '2026-05-08 13:00:00',
                    ],
                ];
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function previewQueue(?string $queueName, int $limit = 10): array
            {
                return [
                    [
                        'position' => 1,
                        'display_name' => 'FetchSourceJob',
                        'job_uuid' => 'job-123',
                        'attempts' => 0,
                        'redelivered' => false,
                        'routing_key' => 'crawler_tasks',
                        'exchange' => 'news.jobs',
                        'payload_preview' => '{"displayName":"FetchSourceJob"}',
                        'payload_bytes' => 128,
                    ],
                ];
            }

            /**
             * @return array{runtime: string, lines: list<string>, error: ?string}
             */
            public function tailLogs(string $runtime, int $lines = 50): array
            {
                return [
                    'runtime' => $runtime,
                    'lines' => ['[worker] ready'],
                    'error' => null,
                ];
            }
        });

        $response = $this->actingAs($admin)->get('/admin/workers');

        $response
            ->assertOk()
            ->assertSee('Workers')
            ->assertSee('Worker Fleet')
            ->assertSee('Docker Control')
            ->assertSee('Not configured')
            ->assertSee('docker compose --profile queue up -d worker')
            ->assertSee('Run until empty')
            ->assertSee('FetchSourceJob');
    }

    public function test_workers_page_actions_delegate_to_worker_management_service(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $service = new class
        {
            /** @var list<string> */
            public array $calls = [];

            /**
             * @return list<array<string, mixed>>
             */
            public function listWorkers(): array
            {
                return [
                    [
                        'runtime' => 'worker',
                        'queue' => 'crawler_tasks',
                        'queues' => ['crawler_tasks', 'intelligence_tasks', 'media_tasks'],
                        'queue_summaries' => [
                            [
                                'queue' => 'crawler_tasks',
                                'status' => 'ok',
                                'message_count' => 0,
                                'consumer_count' => 1,
                                'failed_count' => 0,
                                'last_failed_at' => null,
                                'error' => null,
                            ],
                            [
                                'queue' => 'intelligence_tasks',
                                'status' => 'ok',
                                'message_count' => 0,
                                'consumer_count' => 0,
                                'failed_count' => 0,
                                'last_failed_at' => null,
                                'error' => null,
                            ],
                            [
                                'queue' => 'media_tasks',
                                'status' => 'ok',
                                'message_count' => 0,
                                'consumer_count' => 0,
                                'failed_count' => 0,
                                'last_failed_at' => null,
                                'error' => null,
                            ],
                        ],
                        'health' => 'running',
                        'message_count' => 0,
                        'consumer_count' => 1,
                        'failed_count' => 0,
                        'last_failed_at' => null,
                        'memory_bytes' => 1024,
                        'memory_human' => '1 KB',
                        'cpu_percent' => 0.1,
                        'replica_count' => 2,
                        'running_replica_count' => 1,
                        'uptime_human' => '1m',
                        'restart_count' => 0,
                        'last_heartbeat_at' => now()->toIso8601String(),
                        'heartbeat_stale' => false,
                        'last_processed_job' => 'FetchSourceJob',
                        'last_failed_job' => null,
                        'last_error_summary' => null,
                        'operator_commands' => [],
                    ],
                ];
            }

            /**
             * @return array{configured: bool, backend: string, message: ?string}
             */
            public function dockerControlStatus(): array
            {
                return [
                    'configured' => true,
                    'backend' => 'http',
                    'message' => null,
                ];
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function recentFailedJobs(): array
            {
                return [];
            }

            /**
             * @return list<array<string, mixed>>
             */
            public function previewQueue(?string $queueName, int $limit = 10): array
            {
                return [];
            }

            /**
             * @return array{runtime: string, lines: list<string>, error: ?string}
             */
            public function tailLogs(string $runtime, int $lines = 50): array
            {
                return ['runtime' => $runtime, 'lines' => [], 'error' => null];
            }

            /**
             * @return array{success: bool, runtime: string, message: string}
             */
            public function startWorker(string $runtime): array
            {
                $this->calls[] = 'start:'.$runtime;

                return ['success' => true, 'runtime' => $runtime, 'message' => 'started'];
            }

            /**
             * @return array{success: bool, runtime: string, message: string}
             */
            public function stopWorker(string $runtime): array
            {
                $this->calls[] = 'stop:'.$runtime;

                return ['success' => true, 'runtime' => $runtime, 'message' => 'stopped'];
            }

            /**
             * @return array{success: bool, runtime: string, message: string}
             */
            public function restartWorker(string $runtime): array
            {
                $this->calls[] = 'restart:'.$runtime;

                return ['success' => true, 'runtime' => $runtime, 'message' => 'restarted'];
            }

            /**
             * @return array{success: bool, message: string}
             */
            public function softRestartWorkers(): array
            {
                $this->calls[] = 'soft-restart';

                return ['success' => true, 'message' => 'queue:restart'];
            }

            /**
             * @return array{processed: bool, queue: string, before: int, after: int}
             */
            public function runOneJob(string $queue): array
            {
                $this->calls[] = 'run-one:'.$queue;

                return ['processed' => true, 'queue' => $queue, 'before' => 1, 'after' => 0];
            }

            /**
             * @return array{processed: bool, queue: string, before: int, after: int, max_jobs: int}
             */
            public function runUntilEmpty(string $queue, ?int $maxJobs = null): array
            {
                $this->calls[] = 'run-until-empty:'.$queue.':'.(string) $maxJobs;

                return ['processed' => true, 'queue' => $queue, 'before' => 2, 'after' => 0, 'max_jobs' => $maxJobs ?? 25];
            }

            /**
             * @return array{success: bool, queue: string, removed: int}
             */
            public function purgeQueue(string $queue): array
            {
                $this->calls[] = 'purge:'.$queue;

                return ['success' => true, 'queue' => $queue, 'removed' => 3];
            }
        };

        $this->app->instance(WorkerManagementService::class, $service);

        $this->livewireAs($admin, Workers::class)
            ->call('startWorker', 'worker')
            ->call('stopWorker', 'worker')
            ->call('restartWorker', 'worker')
            ->call('softRestartWorkers')
            ->call('runOneJob', 'crawler_tasks')
            ->call('runUntilEmpty', 'crawler_tasks')
            ->call('purgeQueue', 'crawler_tasks')
            ->assertHasNoErrors();

        $this->assertSame([
            'start:worker',
            'stop:worker',
            'restart:worker',
            'soft-restart',
            'run-one:crawler_tasks',
            'run-until-empty:crawler_tasks:',
            'purge:crawler_tasks',
        ], $service->calls);
    }
}
