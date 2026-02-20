<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Messaging;

use Modules\Crawler\Domain\Contracts\RawPublisher as RawPublisherContract;
use Modules\Shared\Domain\DTO\RawNewsData;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

final readonly class RawPublisher implements RawPublisherContract
{
    public function __construct(
        private AMQPStreamConnection $connection,
        private string $exchange = 'news_flow',
        private string $routingKey = 'raw.created',
    ) {}

    public function publish(RawNewsData $raw): void
    {
        if (! $this->connection->isConnected()) {
            $this->connection->reconnect();
        }

        try {
            $channel = $this->connection->channel();
        } catch (Throwable $e) {
            $this->connection->reconnect();
            $channel = $this->connection->channel();
        }

        $payload = json_encode([
            'sourceId' => $raw->sourceId,
            'externalId' => $raw->externalId,
            'title' => $raw->title,
            'link' => $raw->link,
            'content' => $raw->content,
            'publishedAt' => $raw->publishedAt->toIso8601String(),
            'language' => $raw->language,
            'metadata' => $raw->metadata,
            'imageUrl' => $raw->imageUrl,
            'media' => $raw->media,
            'fingerprint' => $raw->fingerprint,
        ], JSON_THROW_ON_ERROR);

        $message = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => 2,
            'message_id' => $raw->fingerprint,
        ]);

        $channel->basic_publish($message, $this->exchange, $this->routingKey, true);
        $channel->close();
    }
}
