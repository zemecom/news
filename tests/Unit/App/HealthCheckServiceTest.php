<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\HealthCheckService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class HealthCheckServiceTest extends TestCase
{
    public function test_check_reports_all_dependencies_as_ok_when_they_respond(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andReturn(new class
            {
                public function getPdo(): object
                {
                    return new stdClass;
                }
            });

        Redis::shouldReceive('command')
            ->once()
            ->with('ping')
            ->andReturn('PONG');

        $channel = $this->createMock(\PhpAmqpLib\Channel\AMQPChannel::class);
        $channel->expects($this->once())
            ->method('close');

        $amqp = $this->createMock(AMQPStreamConnection::class);
        $amqp->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $service = new HealthCheckService($amqp);

        $this->assertSame([
            'db' => 'ok',
            'redis' => 'ok',
            'rabbitmq' => 'ok',
        ], $service->check());
    }

    public function test_check_returns_failures_when_dependencies_throw(): void
    {
        DB::shouldReceive('connection')
            ->once()
            ->andThrow(new RuntimeException('db down'));

        Redis::shouldReceive('command')
            ->once()
            ->with('ping')
            ->andThrow(new RuntimeException('redis down'));

        $amqp = $this->createMock(AMQPStreamConnection::class);
        $amqp->expects($this->once())
            ->method('channel')
            ->willThrowException(new RuntimeException('rabbitmq down'));

        $service = new HealthCheckService($amqp);

        $this->assertSame([
            'db' => 'fail',
            'redis' => 'fail',
            'rabbitmq' => 'fail',
        ], $service->check());
    }
}
