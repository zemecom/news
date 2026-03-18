<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Modules\Intelligence\Infrastructure\Codex\CodexProcessRunner;
use Tests\TestCase;

final class CodexProcessRunnerTest extends TestCase
{
    public function test_run_json_session_sends_messages_sequentially_when_protocol_requires_handshake(): void
    {
        $runner = new CodexProcessRunner;

        $script = <<<'PHP'
$stdin = fopen('php://stdin', 'r');
$stdout = fopen('php://stdout', 'w');

$first = fgets($stdin);
if ($first === false) {
    exit(1);
}

$read = [$stdin];
$write = null;
$except = null;
$hasBufferedSecondMessage = stream_select($read, $write, $except, 0, 0) > 0;

fwrite($stdout, json_encode(['id' => 1, 'result' => ['stage' => 'initialized']], JSON_THROW_ON_ERROR) . PHP_EOL);
fflush($stdout);

if ($hasBufferedSecondMessage) {
    exit(0);
}

$second = fgets($stdin);
if ($second === false) {
    exit(0);
}

fwrite($stdout, json_encode(['id' => 2, 'result' => ['stage' => 'ready']], JSON_THROW_ON_ERROR) . PHP_EOL);
fflush($stdout);
PHP;

        $result = $runner->runJsonSession(
            command: [PHP_BINARY, '-r', $script],
            messages: [
                ['id' => 1, 'method' => 'initialize', 'params' => []],
                ['id' => 2, 'method' => 'account/login/start', 'params' => ['type' => 'chatgpt']],
            ],
            timeoutSeconds: 2,
        );

        $this->assertSame(0, $result['exit_code']);
        $this->assertCount(2, $result['decoded']);
        $this->assertSame('initialized', $result['decoded'][0]['result']['stage']);
        $this->assertSame('ready', $result['decoded'][1]['result']['stage']);
    }
}
