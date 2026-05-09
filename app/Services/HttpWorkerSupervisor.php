<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\WorkerSupervisor;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final readonly class HttpWorkerSupervisor implements WorkerSupervisor
{
    /**
     * @param  list<string>  $runtimes
     */
    public function __construct(
        private Client $http,
        private string $baseUrl,
        private string $token,
        private bool $configured,
        private array $runtimes,
    ) {}

    public function listRuntimes(): array
    {
        return $this->runtimes;
    }

    public function status(string $runtime): array
    {
        $this->guardRuntime($runtime);

        try {
            [$statusCode, $payload] = $this->request('GET', sprintf('/v1/runtimes/%s', rawurlencode($runtime)));
        } catch (GuzzleException|RuntimeException $e) {
            return [
                'runtime' => $runtime,
                'service' => $runtime,
                'state' => 'unavailable',
                'container_present' => false,
                'replica_count' => 0,
                'running_replica_count' => 0,
                'memory_bytes' => null,
                'cpu_percent' => null,
                'uptime_seconds' => null,
                'restart_count' => null,
                'started_at' => null,
                'error' => $e->getMessage(),
            ];
        }

        if ($statusCode >= 400) {
            return [
                'runtime' => $runtime,
                'service' => $runtime,
                'state' => 'unavailable',
                'container_present' => false,
                'replica_count' => 0,
                'running_replica_count' => 0,
                'memory_bytes' => null,
                'cpu_percent' => null,
                'uptime_seconds' => null,
                'restart_count' => null,
                'started_at' => null,
                'error' => (string) ($payload['error'] ?? 'Worker control request failed.'),
            ];
        }

        return [
            'runtime' => $runtime,
            'service' => is_string($payload['service'] ?? null) ? $payload['service'] : $runtime,
            'state' => is_string($payload['state'] ?? null) ? $payload['state'] : 'unavailable',
            'container_present' => (bool) ($payload['container_present'] ?? false),
            'replica_count' => isset($payload['replica_count']) ? (int) $payload['replica_count'] : 0,
            'running_replica_count' => isset($payload['running_replica_count']) ? (int) $payload['running_replica_count'] : 0,
            'memory_bytes' => isset($payload['memory_bytes']) ? (int) $payload['memory_bytes'] : null,
            'cpu_percent' => isset($payload['cpu_percent']) ? (float) $payload['cpu_percent'] : null,
            'uptime_seconds' => isset($payload['uptime_seconds']) ? (int) $payload['uptime_seconds'] : null,
            'restart_count' => isset($payload['restart_count']) ? (int) $payload['restart_count'] : null,
            'started_at' => is_string($payload['started_at'] ?? null) ? $payload['started_at'] : null,
            'error' => is_string($payload['error'] ?? null) ? $payload['error'] : null,
        ];
    }

    public function start(string $runtime): array
    {
        return $this->action('start', $runtime);
    }

    public function stop(string $runtime): array
    {
        return $this->action('stop', $runtime);
    }

    public function restart(string $runtime): array
    {
        return $this->action('restart', $runtime);
    }

    public function tailLogs(string $runtime, int $lines = 50): array
    {
        $this->guardRuntime($runtime);

        try {
            [, $payload] = $this->request('GET', sprintf('/v1/runtimes/%s/logs?lines=%d', rawurlencode($runtime), max(1, $lines)));
        } catch (GuzzleException|RuntimeException $e) {
            return [
                'runtime' => $runtime,
                'lines' => [],
                'error' => $e->getMessage(),
            ];
        }

        $linesPayload = $payload['lines'] ?? [];
        $logLines = is_array($linesPayload)
            ? array_values(array_filter($linesPayload, 'is_string'))
            : [];

        return [
            'runtime' => $runtime,
            'lines' => $logLines,
            'error' => is_string($payload['error'] ?? null) ? $payload['error'] : null,
        ];
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function backendLabel(): string
    {
        return 'http';
    }

    /**
     * @return array<string, mixed>
     */
    private function action(string $action, string $runtime): array
    {
        $this->guardRuntime($runtime);

        try {
            [$statusCode, $payload] = $this->request('POST', sprintf('/v1/runtimes/%s/%s', rawurlencode($runtime), $action));
        } catch (GuzzleException|RuntimeException $e) {
            return [
                'success' => false,
                'runtime' => $runtime,
                'message' => $e->getMessage(),
            ];
        }

        return [
            'success' => $statusCode < 400 && (bool) ($payload['success'] ?? true),
            'runtime' => $runtime,
            'message' => is_string($payload['message'] ?? null) ? $payload['message'] : 'Worker action completed.',
        ];
    }

    /**
     * @return array{0:int,1:array<string, mixed>}
     */
    private function request(string $method, string $uri): array
    {
        $response = $this->http->request($method, $this->url($uri), [
            'headers' => [
                'Authorization' => 'Bearer '.$this->token,
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $decoded = json_decode((string) $response->getBody(), true);
        $payload = is_array($decoded) ? $decoded : [];

        return [$response->getStatusCode(), $payload];
    }

    private function url(string $uri): string
    {
        return rtrim($this->baseUrl, '/').$uri;
    }

    private function guardRuntime(string $runtime): void
    {
        if (! in_array($runtime, $this->runtimes, true)) {
            throw new RuntimeException(sprintf('Runtime "%s" is not allowed for worker supervision.', $runtime));
        }
    }
}
