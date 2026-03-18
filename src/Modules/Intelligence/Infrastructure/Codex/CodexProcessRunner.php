<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Codex;

use JsonException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class CodexProcessRunner implements CodexProcessRunnerContract
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
    ): array {
        $process = new Process($command, $cwd, $env, $input, $timeoutSeconds);
        $process->run();

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => $process->getOutput(),
            'error_output' => $process->getErrorOutput(),
        ];
    }

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
    ): array {
        $input = new InputStream;
        $output = '';
        $errorOutput = '';

        $process = new Process($command, $cwd, $env, $input, $timeoutSeconds);
        $process->start();

        $deadline = microtime(true) + $timeoutSeconds;

        foreach ($messages as $message) {
            $input->write($this->encodeMessage($message));

            $messageId = $message['id'] ?? null;
            if (is_int($messageId)) {
                $this->waitForResponseId($process, $output, $errorOutput, $messageId, $deadline);
            }
        }

        $input->close();
        $process->wait(function (string $type, string $buffer) use (&$output, &$errorOutput): void {
            if ($type === Process::OUT) {
                $output .= $buffer;

                return;
            }

            $errorOutput .= $buffer;
        });

        return [
            'exit_code' => $process->getExitCode() ?? 1,
            'output' => $output,
            'error_output' => $errorOutput,
            'decoded' => $this->decodeOutputLines($output),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function encodeMessage(array $message): string
    {
        try {
            return json_encode($message, JSON_THROW_ON_ERROR)."\n";
        } catch (JsonException $e) {
            throw new CodexException('Failed to encode Codex app-server request.', previous: $e);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeOutputLines(string $output): array
    {
        $decoded = [];
        $lines = preg_split('/\r?\n/', trim($output)) ?: [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            try {
                /** @var array<string, mixed> $payload */
                $payload = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }

            $decoded[] = $payload;
        }

        return $decoded;
    }

    private function waitForResponseId(Process $process, string &$output, string &$errorOutput, int $messageId, float $deadline): void
    {
        while (microtime(true) < $deadline) {
            $output .= $process->getIncrementalOutput();
            $errorOutput .= $process->getIncrementalErrorOutput();

            foreach ($this->decodeOutputLines($output) as $payload) {
                if (($payload['id'] ?? null) === $messageId) {
                    return;
                }
            }

            if (! $process->isRunning()) {
                return;
            }

            usleep(20_000);
        }
    }
}
