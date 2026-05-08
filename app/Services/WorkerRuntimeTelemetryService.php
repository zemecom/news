<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

final readonly class WorkerRuntimeTelemetryService
{
    public function __construct(private string $store = 'redis') {}

    public function recordHeartbeat(string $runtime): void
    {
        $snapshot = $this->baseSnapshot($runtime);
        $snapshot['last_heartbeat_at'] = now()->toIso8601String();

        $this->putSnapshot($runtime, $snapshot);
    }

    public function recordProcessedJob(string $runtime, string $jobName): void
    {
        $snapshot = $this->baseSnapshot($runtime);
        $snapshot['last_processed_job'] = $jobName;
        $snapshot['last_heartbeat_at'] ??= now()->toIso8601String();

        $this->putSnapshot($runtime, $snapshot);
    }

    public function recordFailedJob(string $runtime, string $jobName, string $summary): void
    {
        $snapshot = $this->baseSnapshot($runtime);
        $snapshot['last_failed_job'] = $jobName;
        $snapshot['last_error_summary'] = $summary;
        $snapshot['last_heartbeat_at'] ??= now()->toIso8601String();

        $this->putSnapshot($runtime, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function putSnapshot(string $runtime, array $snapshot): void
    {
        $this->store()->forever($this->key($runtime), [
            'last_heartbeat_at' => is_string($snapshot['last_heartbeat_at'] ?? null) ? $snapshot['last_heartbeat_at'] : null,
            'last_processed_job' => is_string($snapshot['last_processed_job'] ?? null) ? $snapshot['last_processed_job'] : null,
            'last_failed_job' => is_string($snapshot['last_failed_job'] ?? null) ? $snapshot['last_failed_job'] : null,
            'last_error_summary' => is_string($snapshot['last_error_summary'] ?? null) ? $snapshot['last_error_summary'] : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(string $runtime): array
    {
        $snapshot = $this->baseSnapshot($runtime);
        $lastHeartbeat = $snapshot['last_heartbeat_at'];
        $heartbeatTtl = max(1, (int) config('workers.heartbeat_ttl_seconds', 120));

        $snapshot['heartbeat_stale'] = is_string($lastHeartbeat)
            ? CarbonImmutable::parse($lastHeartbeat)->lt(now()->subSeconds($heartbeatTtl))
            : false;

        return $snapshot;
    }

    /**
     * @return array<string, mixed>
     */
    private function baseSnapshot(string $runtime): array
    {
        $stored = $this->store()->get($this->key($runtime), []);
        $snapshot = is_array($stored) ? $stored : [];

        return [
            'last_heartbeat_at' => is_string($snapshot['last_heartbeat_at'] ?? null) ? $snapshot['last_heartbeat_at'] : null,
            'last_processed_job' => is_string($snapshot['last_processed_job'] ?? null) ? $snapshot['last_processed_job'] : null,
            'last_failed_job' => is_string($snapshot['last_failed_job'] ?? null) ? $snapshot['last_failed_job'] : null,
            'last_error_summary' => is_string($snapshot['last_error_summary'] ?? null) ? $snapshot['last_error_summary'] : null,
        ];
    }

    private function store(): Repository
    {
        return Cache::store($this->store);
    }

    private function key(string $runtime): string
    {
        return sprintf('worker-runtime:%s', $runtime);
    }
}
