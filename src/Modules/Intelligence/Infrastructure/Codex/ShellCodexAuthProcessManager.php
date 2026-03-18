<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use Symfony\Component\Process\Process;

final class ShellCodexAuthProcessManager implements CodexAuthProcessManager
{
    public function startDeviceAuth(string $binary, string $codexHome, string $outputPath): int
    {
        $command = sprintf(
            'nohup %s login --device-auth > %s 2>&1 < /dev/null & echo $!',
            escapeshellcmd($binary),
            escapeshellarg($outputPath),
        );

        $process = Process::fromShellCommandline($command, null, [
            'CODEX_HOME' => $codexHome,
            'HOME' => '/home/www-data',
        ]);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new CodexException('Failed to start background Codex device auth process: '.$process->getErrorOutput());
        }

        $pid = (int) trim($process->getOutput());
        if ($pid <= 0) {
            throw new CodexException('Failed to capture PID for background Codex device auth process.');
        }

        return $pid;
    }

    public function isRunning(int $pid): bool
    {
        return $pid > 0 && function_exists('posix_kill') && @posix_kill($pid, 0);
    }

    public function terminate(int $pid): void
    {
        if (! $this->isRunning($pid)) {
            return;
        }

        @posix_kill($pid, SIGTERM);
        usleep(300_000);

        @posix_kill($pid, SIGKILL);
    }
}
