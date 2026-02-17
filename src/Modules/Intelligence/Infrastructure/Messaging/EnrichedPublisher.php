<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\Messaging;

use Modules\Intelligence\Domain\Contracts\EnrichedPublisher as EnrichedPublisherContract;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

final readonly class EnrichedPublisher implements EnrichedPublisherContract
{
    public function __construct(
        private AMQPStreamConnection $connection,
        private string $exchange = 'news_flow',
        private string $readyRoutingKey = 'enriched.ready',
        private string $importantRoutingKey = 'enriched.ready.important',
        private string $rejectedRoutingKey = 'enriched.rejected',
    ) {}

    public function publish(EnrichedNewsData $enriched): void
    {
        $channel = $this->connection->channel();

        $payload = json_encode([
            'rawId' => $enriched->rawId,
            'titleGenerated' => $enriched->titleGenerated,
            'contentTranslated' => $enriched->contentTranslated,
            'sentiment' => $enriched->sentiment,
            'category' => $enriched->category,
            'tags' => $enriched->tags,
            'importance' => $enriched->importance,
            'status' => $enriched->status->value,
            'moderationReason' => $enriched->moderationReason,
            'fingerprint' => $enriched->fingerprint,
        ], JSON_THROW_ON_ERROR);

        $headers = new AMQPTable;
        if ($enriched->importance) {
            $headers->set('x-important', 1);
        }

        $message = new AMQPMessage($payload, [
            'content_type' => 'application/json',
            'delivery_mode' => 2,
            'message_id' => $enriched->fingerprint,
            'application_headers' => $headers,
        ]);

        $routingKey = $enriched->status === NewsStatus::REJECTED
            ? $this->rejectedRoutingKey
            : $this->readyRoutingKey;

        $channel->basic_publish($message, $this->exchange, $routingKey, true);

        if ($enriched->status !== NewsStatus::REJECTED && $enriched->importance) {
            $channel->basic_publish($message, $this->exchange, $this->importantRoutingKey, true);
        }

        $channel->close();
    }
}
