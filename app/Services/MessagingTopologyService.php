<?php

declare(strict_types=1);

namespace App\Services;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

final readonly class MessagingTopologyService
{
    public function __construct(private AMQPStreamConnection $connection) {}

    public function declareTopology(): void
    {
        $channel = $this->connection->channel();

        $this->declareTopologyOn($channel);

        $channel->close();
    }

    public function declareTopologyOn(AMQPChannel $channel): void
    {
        $this->declareLaravelQueueTopologyOn($channel);
        $this->declareDeliveryMessagingTopologyOn($channel);
    }

    private function declareLaravelQueueTopologyOn(AMQPChannel $channel): void
    {
        $exchangeName = (string) config('queue.connections.rabbitmq.options.queue.exchange', 'news.jobs');
        $exchangeType = (string) config('queue.connections.rabbitmq.options.queue.exchange_type', 'direct');

        $channel->exchange_declare(
            exchange: $exchangeName,
            type: $exchangeType,
            passive: false,
            durable: true,
            auto_delete: false,
        );

        foreach (QueueOverviewService::QUEUES as $queueName) {
            $channel->queue_declare(
                queue: $queueName,
                passive: false,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            $channel->queue_bind($queueName, $exchangeName, $queueName);
        }
    }

    private function declareDeliveryMessagingTopologyOn(AMQPChannel $channel): void
    {
        $exchangeName = (string) config('messaging.exchange.news_flow.name', 'news_flow');
        $exchangeType = (string) config('messaging.exchange.news_flow.type', 'topic');

        $routingKeys = config('messaging.routing_keys', []);
        $queueNames = config('messaging.queues', []);

        $channel->exchange_declare(
            exchange: $exchangeName,
            type: $exchangeType,
            passive: false,
            durable: true,
            auto_delete: false,
        );

        $feed = (string) ($queueNames['delivery_feed'] ?? 'queue.delivery_feed');
        $push = (string) ($queueNames['delivery_push'] ?? 'queue.delivery_push');

        $this->declareQueue($channel, $feed, quorum: false);
        $this->declareQueue($channel, $push, quorum: true);

        $channel->queue_bind($feed, $exchangeName, (string) ($routingKeys['enriched_ready'] ?? 'enriched.ready'));
        $channel->queue_bind($push, $exchangeName, (string) ($routingKeys['enriched_ready_important'] ?? 'enriched.ready.important'));
    }

    private function declareQueue(AMQPChannel $channel, string $name, bool $quorum): void
    {
        $arguments = $quorum ? new AMQPTable(['x-queue-type' => 'quorum']) : [];

        $channel->queue_declare(
            queue: $name,
            passive: false,
            durable: true,
            exclusive: false,
            auto_delete: false,
            arguments: $arguments,
        );
    }
}
