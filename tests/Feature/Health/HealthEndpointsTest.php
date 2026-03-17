<?php

declare(strict_types=1);

namespace Tests\Feature\Health;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class HealthEndpointsTest extends TestCase
{
    public function test_live_endpoint_reports_application_is_alive(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertExactJson([
                'status' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_ok_when_all_checks_pass(): void
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

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('close');

        $amqp = $this->createMock(AMQPStreamConnection::class);
        $amqp->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $this->app->instance(AMQPStreamConnection::class, $amqp);

        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson([
                'db' => 'ok',
                'redis' => 'ok',
                'rabbitmq' => 'ok',
            ]);
    }

    public function test_ready_endpoint_returns_503_when_any_dependency_fails(): void
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

        $this->app->instance(AMQPStreamConnection::class, $amqp);

        $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertExactJson([
                'db' => 'fail',
                'redis' => 'fail',
                'rabbitmq' => 'fail',
            ]);
    }
}
