<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

interface CodexAuthProcessManager
{
    public function startDeviceAuth(string $binary, string $codexHome, string $outputPath): int;

    public function isRunning(int $pid): bool;

    public function terminate(int $pid): void;
}
