<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\WorkerSupervisor;
use Illuminate\Support\Facades\Artisan;

final readonly class WorkerManagementService
{
    public function __construct(
        private QueueOverviewService $overview,
        private QueueManagementService $queueManagement,
        private WorkerSupervisor $supervisor,
        private WorkerRuntimeTelemetryService $telemetry,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listWorkers(): array
    {
        $summaries = collect($this->overview->getQueueSummaries())->keyBy('queue');
        $workers = [];

        foreach ($this->runtimes() as $runtime => $runtimeConfig) {
            $queue = (string) ($runtimeConfig['queue'] ?? '');
            $summary = $summaries->get($queue, [
                'status' => 'unavailable',
                'message_count' => null,
                'consumer_count' => null,
                'failed_count' => 0,
                'last_failed_at' => null,
                'error' => 'Queue summary is unavailable.',
            ]);
            $supervisorStatus = $this->supervisor->status($runtime);
            $telemetry = $this->telemetry->snapshot($runtime);

            $memoryBytes = isset($supervisorStatus['memory_bytes']) ? (int) $supervisorStatus['memory_bytes'] : null;
            $uptimeSeconds = isset($supervisorStatus['uptime_seconds']) ? (int) $supervisorStatus['uptime_seconds'] : null;

            $workers[] = [
                'runtime' => $runtime,
                'service' => (string) ($runtimeConfig['service'] ?? $runtime),
                'queue' => $queue,
                'health' => $this->health($supervisorStatus, $summary, $telemetry),
                'supervisor_state' => (string) ($supervisorStatus['state'] ?? 'unavailable'),
                'message_count' => $summary['message_count'],
                'consumer_count' => $summary['consumer_count'],
                'failed_count' => (int) $summary['failed_count'],
                'last_failed_at' => $summary['last_failed_at'],
                'memory_bytes' => $memoryBytes,
                'memory_human' => $this->humanBytes($memoryBytes),
                'cpu_percent' => isset($supervisorStatus['cpu_percent']) ? (float) $supervisorStatus['cpu_percent'] : null,
                'uptime_seconds' => $uptimeSeconds,
                'uptime_human' => $this->humanDuration($uptimeSeconds),
                'restart_count' => isset($supervisorStatus['restart_count']) ? (int) $supervisorStatus['restart_count'] : null,
                'last_heartbeat_at' => $telemetry['last_heartbeat_at'],
                'heartbeat_stale' => (bool) ($telemetry['heartbeat_stale'] ?? false),
                'last_processed_job' => $telemetry['last_processed_job'],
                'last_failed_job' => $telemetry['last_failed_job'],
                'last_error_summary' => $telemetry['last_error_summary'] ?? $supervisorStatus['error'] ?? $summary['error'] ?? null,
                'started_at' => $supervisorStatus['started_at'] ?? null,
                'operator_commands' => is_array($supervisorStatus['operator_commands'] ?? null)
                    ? $supervisorStatus['operator_commands']
                    : $this->operatorCommands($runtime),
            ];
        }

        return $workers;
    }

    /**
     * @return array<string, mixed>
     */
    public function dockerControlStatus(): array
    {
        return [
            'configured' => $this->supervisor->isConfigured(),
            'backend' => $this->supervisor->backendLabel(),
            'message' => $this->supervisor->isConfigured()
                ? null
                : 'Docker control is not configured in app container.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentFailedJobs(int $limit = 10): array
    {
        return $this->overview->getRecentFailedJobs($limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function previewQueue(?string $queueName, int $limit = 10): array
    {
        $resolvedQueue = is_string($queueName) && $queueName !== ''
            ? $queueName
            : $this->defaultQueue();

        return $resolvedQueue !== null
            ? $this->queueManagement->previewQueue($resolvedQueue, $limit)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function tailLogs(string $runtime, ?int $lines = null): array
    {
        return $this->supervisor->tailLogs(
            $runtime,
            $lines ?? (int) config('workers.supervisor.log_tail_lines', 50),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function startWorker(string $runtime): array
    {
        return $this->supervisor->start($runtime);
    }

    /**
     * @return array<string, mixed>
     */
    public function stopWorker(string $runtime): array
    {
        return $this->supervisor->stop($runtime);
    }

    /**
     * @return array<string, mixed>
     */
    public function restartWorker(string $runtime): array
    {
        return $this->supervisor->restart($runtime);
    }

    /**
     * @return array<string, mixed>
     */
    public function softRestartWorkers(): array
    {
        $exitCode = Artisan::call('queue:restart');
        $output = trim(Artisan::output());

        return [
            'success' => $exitCode === 0,
            'message' => $exitCode === 0
                ? 'queue:restart broadcasted to Laravel queue workers.'
                : ($output !== '' ? $output : 'queue:restart failed.'),
            'exit_code' => $exitCode,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function runOneJob(string $queue): array
    {
        return $this->queueManagement->processOneQueueJob($queue);
    }

    /**
     * @return array<string, mixed>
     */
    public function runUntilEmpty(string $queue, ?int $maxJobs = null): array
    {
        return $this->queueManagement->runUntilEmpty(
            $queue,
            $maxJobs ?? (int) config('workers.run_until_empty_max_jobs', 25),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function purgeQueue(string $queue): array
    {
        return [
            'success' => true,
            'queue' => $queue,
            'removed' => $this->queueManagement->purgeQueue($queue),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runtimes(): array
    {
        $runtimes = config('workers.runtimes', []);

        return is_array($runtimes) ? $runtimes : [];
    }

    private function defaultQueue(): ?string
    {
        foreach ($this->runtimes() as $runtimeConfig) {
            $queue = $runtimeConfig['queue'] ?? null;

            if (is_string($queue) && $queue !== '') {
                return $queue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $supervisorStatus
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $telemetry
     */
    private function health(array $supervisorStatus, array $summary, array $telemetry): string
    {
        $supervisorState = (string) ($supervisorStatus['state'] ?? 'unavailable');

        if ($supervisorState === 'not_configured') {
            return 'not_configured';
        }

        if ($supervisorState === 'stopped') {
            return 'stopped';
        }

        if ($supervisorState === 'unavailable' || (string) ($summary['status'] ?? 'ok') === 'unavailable') {
            return 'unavailable';
        }

        $hasBacklogWithoutConsumer = ((int) ($summary['message_count'] ?? 0)) > 0 && ((int) ($summary['consumer_count'] ?? 0)) <= 0;
        $heartbeatStale = (bool) ($telemetry['heartbeat_stale'] ?? false);
        $hasRecentFailures = ((int) ($summary['failed_count'] ?? 0)) > 0;

        if ($supervisorState === 'running' && ($hasBacklogWithoutConsumer || $heartbeatStale || $hasRecentFailures)) {
            return 'degraded';
        }

        return 'running';
    }

    /**
     * @return array<string, string>
     */
    private function operatorCommands(string $runtime): array
    {
        $commands = config('workers.operator_commands', []);
        $restartTemplate = is_string($commands['restart_runtime_template'] ?? null)
            ? $commands['restart_runtime_template']
            : 'docker compose restart %s';

        return [
            'start_all' => is_string($commands['start_all'] ?? null) ? $commands['start_all'] : 'make worker-up',
            'restart_runtime' => sprintf($restartTemplate, $runtime),
        ];
    }

    private function humanBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'n/a';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / 1024 / 1024, 1).' MB';
        }

        return round($bytes / 1024 / 1024 / 1024, 1).' GB';
    }

    private function humanDuration(?int $seconds): string
    {
        if ($seconds === null) {
            return 'n/a';
        }

        if ($seconds < 60) {
            return $seconds.'s';
        }

        if ($seconds < 3600) {
            return floor($seconds / 60).'m';
        }

        return floor($seconds / 3600).'h';
    }
}
