<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\HttpWorkerSupervisor;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Tests\TestCase;

final class HttpWorkerSupervisorTest extends TestCase
{
    public function test_it_maps_runtime_status_payload_from_http_api(): void
    {
        /** @var list<array{request: RequestInterface}> $history */
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'runtime' => 'crawler-worker',
                'service' => 'crawler-worker',
                'state' => 'running',
                'container_present' => true,
                'memory_bytes' => 104857600,
                'cpu_percent' => 1.5,
                'uptime_seconds' => 420,
                'restart_count' => 2,
                'started_at' => '2026-05-08T12:00:00+00:00',
                'error' => null,
            ], JSON_THROW_ON_ERROR)),
        ]));
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
        ]);

        $supervisor = new HttpWorkerSupervisor(
            http: $client,
            baseUrl: 'http://worker-control:8081',
            token: 'secret-token',
            configured: true,
            runtimes: ['crawler-worker'],
        );

        $status = $supervisor->status('crawler-worker');
        /** @var array{request: RequestInterface} $firstTransaction */
        $firstTransaction = $history[0];

        $this->assertSame('running', $status['state']);
        $this->assertSame(104857600, $status['memory_bytes']);
        $this->assertSame(1.5, $status['cpu_percent']);
        $this->assertSame('Bearer secret-token', $firstTransaction['request']->getHeaderLine('Authorization'));
        $this->assertSame('/v1/runtimes/crawler-worker', $firstTransaction['request']->getUri()->getPath());
    }

    public function test_it_maps_log_tail_payload_and_runtime_actions(): void
    {
        /** @var list<array{request: RequestInterface}> $history */
        $history = [];
        $handler = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'runtime' => 'crawler-worker',
                'lines' => ['line one', 'line two'],
            ], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode([
                'success' => true,
                'runtime' => 'crawler-worker',
                'message' => 'Runtime restarted.',
            ], JSON_THROW_ON_ERROR)),
        ]));
        $handler->push(Middleware::history($history));

        $client = new Client([
            'handler' => $handler,
        ]);

        $supervisor = new HttpWorkerSupervisor(
            http: $client,
            baseUrl: 'http://worker-control:8081',
            token: 'secret-token',
            configured: true,
            runtimes: ['crawler-worker'],
        );

        $logs = $supervisor->tailLogs('crawler-worker', 20);
        $result = $supervisor->restart('crawler-worker');
        /** @var array{request: RequestInterface} $restartTransaction */
        $restartTransaction = $history[1];

        $this->assertSame(['line one', 'line two'], $logs['lines']);
        $this->assertTrue($result['success']);
        $this->assertSame('POST', $restartTransaction['request']->getMethod());
        $this->assertSame('/v1/runtimes/crawler-worker/restart', $restartTransaction['request']->getUri()->getPath());
    }

    public function test_it_returns_unavailable_result_when_http_backend_fails(): void
    {
        $client = new Client([
            'handler' => HandlerStack::create(new MockHandler([
                new Response(500, [], json_encode([
                    'error' => 'Docker inspect failed.',
                ], JSON_THROW_ON_ERROR)),
            ])),
            'http_errors' => false,
        ]);

        $supervisor = new HttpWorkerSupervisor(
            http: $client,
            baseUrl: 'http://worker-control:8081',
            token: 'secret-token',
            configured: true,
            runtimes: ['crawler-worker'],
        );

        $status = $supervisor->status('crawler-worker');

        $this->assertSame('unavailable', $status['state']);
        $this->assertStringContainsString('Docker inspect failed', (string) $status['error']);
    }
}
