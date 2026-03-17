<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\MessagingTopologyService;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;
use Tests\TestCase;

final class MessagingTopologyServiceTest extends TestCase
{
    public function test_declare_topology_declares_exchange_queues_and_bindings(): void
    {
        config()->set('messaging.exchange.news_flow.name', 'news_flow');
        config()->set('messaging.exchange.news_flow.type', 'topic');
        config()->set('messaging.queues', [
            'delivery_feed' => 'queue.delivery_feed',
            'delivery_push' => 'queue.delivery_push',
        ]);
        config()->set('messaging.routing_keys', [
            'enriched_ready' => 'enriched.ready',
            'enriched_ready_important' => 'enriched.ready.important',
        ]);

        $queueDeclares = [];
        $queueBinds = [];

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('exchange_declare')
            ->with('news_flow', 'topic', false, true, false);
        $channel->expects($this->exactly(2))
            ->method('queue_declare')
            ->willReturnCallback(function (...$args) use (&$queueDeclares): void {
                $queueDeclares[] = $args;
            });
        $channel->expects($this->exactly(2))
            ->method('queue_bind')
            ->willReturnCallback(function (...$args) use (&$queueBinds): void {
                $queueBinds[] = $args;
            });
        $channel->expects($this->once())
            ->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $service = new MessagingTopologyService($connection);
        $service->declareTopology();

        $this->assertCount(2, $queueDeclares);
        $this->assertSame('queue.delivery_feed', $queueDeclares[0][0]);
        $this->assertSame(false, $queueDeclares[0][5]);
        $this->assertSame([], $queueDeclares[0][6]);
        $this->assertSame('queue.delivery_push', $queueDeclares[1][0]);
        $this->assertSame(false, $queueDeclares[1][5]);
        $this->assertInstanceOf(AMQPTable::class, $queueDeclares[1][6]);

        $this->assertSame([
            ['queue.delivery_feed', 'news_flow', 'enriched.ready'],
            ['queue.delivery_push', 'news_flow', 'enriched.ready.important'],
        ], array_map(static fn (array $args): array => [$args[0], $args[1], $args[2]], $queueBinds));
    }
}
