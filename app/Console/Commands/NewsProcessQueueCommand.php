<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MessagingTopologyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Shared\Domain\DTO\RawNewsData;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

final class NewsProcessQueueCommand extends Command
{
    protected $signature = 'news:process {--once : Process only one message and exit} {--declare-topology : Ensure queues/exchange before consuming}';

    protected $description = 'Consume raw.created messages from RabbitMQ and run intelligence pipeline.';

    public function handle(
        AMQPStreamConnection $connection,
        MessagingTopologyService $topology,
        NewsProcessingPipeline $pipeline,
    ): int {
        $channel = $connection->channel();

        if ((bool) $this->option('declare-topology')) {
            $topology->declareTopologyOn($channel);
        }

        $queue = (string) config('messaging.queues.news_processing', 'queue.news_processing');
        $dlq = (string) config('messaging.queues.news_processing_dlq', 'queue.news_processing.dlq');
        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        if ((bool) $this->option('once')) {
            $message = $channel->basic_get($queue, false);
            if ($message === null) {
                $this->warn('Queue is empty.');
                $channel->close();

                return self::SUCCESS;
            }

            $this->processMessage($message, $channel, $dlq, $pipeline);
            $channel->close();

            return self::SUCCESS;
        }

        $this->info(sprintf('Waiting for messages on %s ...', $queue));

        $channel->basic_consume(
            queue: $queue,
            no_local: false,
            no_ack: false,
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($channel, $dlq, $pipeline): void {
                $this->processMessage($message, $channel, $dlq, $pipeline);
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();

        return self::SUCCESS;
    }

    private function processMessage(
        AMQPMessage $message,
        \PhpAmqpLib\Channel\AMQPChannel $channel,
        string $dlq,
        NewsProcessingPipeline $pipeline,
    ): void {
        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \RuntimeException('Invalid payload type.');
            }

            $raw = $this->mapRawNews($payload);
            $pipeline->handle($raw);
            $message->ack();

            $this->line(sprintf('Processed fingerprint=%s', $raw->fingerprint));
        } catch (\Throwable $e) {
            $this->sendToDlq($channel, $dlq, $message, $e);
            $message->ack();

            $this->error(sprintf('Failed message: %s', $e->getMessage()));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mapRawNews(array $payload): RawNewsData
    {
        $publishedAt = $payload['publishedAt'] ?? 'now';
        $metadata = $payload['metadata'] ?? [];

        return new RawNewsData(
            sourceId: (int) ($payload['sourceId'] ?? 0),
            externalId: isset($payload['externalId']) ? (string) $payload['externalId'] : null,
            title: (string) ($payload['title'] ?? ''),
            link: (string) ($payload['link'] ?? ''),
            content: (string) ($payload['content'] ?? ''),
            publishedAt: CarbonImmutable::parse((string) $publishedAt),
            language: (string) ($payload['language'] ?? 'en'),
            metadata: is_array($metadata) ? $metadata : [],
            imageUrl: isset($payload['imageUrl']) ? (string) $payload['imageUrl'] : null,
            media: is_array($payload['media'] ?? null) ? $payload['media'] : [],
            fingerprint: (string) ($payload['fingerprint'] ?? ''),
            rawId: null,
        );
    }

    private function sendToDlq(
        \PhpAmqpLib\Channel\AMQPChannel $channel,
        string $dlq,
        AMQPMessage $failedMessage,
        \Throwable $e
    ): void {
        $dlqPayload = json_encode([
            'failed_at' => now()->toIso8601String(),
            'error' => $e->getMessage(),
            'original_body' => $failedMessage->getBody(),
        ], JSON_THROW_ON_ERROR);

        $channel->basic_publish(
            new AMQPMessage($dlqPayload, [
                'content_type' => 'application/json',
                'delivery_mode' => 2,
                'message_id' => (string) ($failedMessage->get_properties()['message_id'] ?? uniqid('dlq-', true)),
            ]),
            '',
            $dlq
        );
    }
}
