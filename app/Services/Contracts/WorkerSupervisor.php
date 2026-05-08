<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface WorkerSupervisor
{
    /**
     * @return list<string>
     */
    public function listRuntimes(): array;

    /**
     * @return array<string, mixed>
     */
    public function status(string $runtime): array;

    /**
     * @return array<string, mixed>
     */
    public function start(string $runtime): array;

    /**
     * @return array<string, mixed>
     */
    public function stop(string $runtime): array;

    /**
     * @return array<string, mixed>
     */
    public function restart(string $runtime): array;

    /**
     * @return array<string, mixed>
     */
    public function tailLogs(string $runtime, int $lines = 50): array;

    public function isConfigured(): bool;

    public function backendLabel(): string;
}
