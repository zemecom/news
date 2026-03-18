<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

interface CodexProcessRunnerContract
{
    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     * @return array{exit_code:int, output:string, error_output:string}
     */
    public function run(
        array $command,
        ?string $cwd = null,
        array $env = [],
        ?int $timeoutSeconds = null,
        ?string $input = null,
    ): array;

    /**
     * @param  list<string>  $command
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, string>  $env
     * @return array{exit_code:int, output:string, error_output:string, decoded:list<array<string, mixed>>}
     */
    public function runJsonSession(
        array $command,
        array $messages,
        ?string $cwd = null,
        array $env = [],
        int $timeoutSeconds = 15,
    ): array;
}
