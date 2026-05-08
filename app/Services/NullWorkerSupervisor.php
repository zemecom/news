<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\WorkerSupervisor;
use RuntimeException;

final class NullWorkerSupervisor implements WorkerSupervisor
{
    public function listRuntimes(): array
    {
        return array_keys($this->runtimes());
    }

    public function status(string $runtime): array
    {
        $runtimeConfig = $this->runtimeConfig($runtime);

        return [
            'runtime' => $runtime,
            'service' => (string) ($runtimeConfig['service'] ?? $runtime),
            'state' => 'not_configured',
            'container_present' => false,
            'memory_bytes' => null,
            'cpu_percent' => null,
            'uptime_seconds' => null,
            'restart_count' => null,
            'started_at' => null,
            'error' => 'Worker control is not configured in app container.',
            'operator_commands' => $this->operatorCommands($runtime),
        ];
    }

    public function start(string $runtime): array
    {
        return $this->disabledActionResult($runtime);
    }

    public function stop(string $runtime): array
    {
        return $this->disabledActionResult($runtime);
    }

    public function restart(string $runtime): array
    {
        return $this->disabledActionResult($runtime);
    }

    public function tailLogs(string $runtime, int $lines = 50): array
    {
        $this->runtimeConfig($runtime);

        return [
            'runtime' => $runtime,
            'lines' => [],
            'error' => 'Worker control is not configured in app container.',
        ];
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function backendLabel(): string
    {
        return 'not-configured';
    }

    /**
     * @return array<string, mixed>
     */
    private function disabledActionResult(string $runtime): array
    {
        $this->runtimeConfig($runtime);

        return [
            'success' => false,
            'runtime' => $runtime,
            'message' => 'Worker control is not configured in app container.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runtimeConfig(string $runtime): array
    {
        $runtimeConfig = $this->runtimes()[$runtime] ?? null;

        if (! is_array($runtimeConfig)) {
            throw new RuntimeException(sprintf('Runtime "%s" is not configured.', $runtime));
        }

        return $runtimeConfig;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function runtimes(): array
    {
        $runtimes = config('workers.runtimes', []);

        return is_array($runtimes) ? $runtimes : [];
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
}
