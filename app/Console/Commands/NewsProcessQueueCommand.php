<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MessagingTopologyService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
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

        $exchange = (string) config('messaging.exchange.news_flow.name', 'news_flow');
        $retryRoutingKey = (string) config('messaging.routing_keys.raw_retry', 'raw.retry');
        $queue = (string) config('messaging.queues.news_processing', 'queue.news_processing');
        $dlq = (string) config('messaging.queues.news_processing_dlq', 'queue.news_processing.dlq');
        $maxAttempts = (int) config('messaging.processing.max_attempts', 5);
        $backoff = $this->normalizeBackoff(config('messaging.processing.retry_backoff_seconds', [5, 15, 60]));
        $channel->basic_qos(prefetch_size: 0, prefetch_count: 1, a_global: false);

        if ((bool) $this->option('once')) {
            $message = $channel->basic_get($queue, false);
            if ($message === null) {
                $this->warn('Queue is empty.');
                $channel->close();

                return self::SUCCESS;
            }

            $this->processMessage(
                message: $message,
                channel: $channel,
                dlq: $dlq,
                pipeline: $pipeline,
                exchange: $exchange,
                retryRoutingKey: $retryRoutingKey,
                maxAttempts: $maxAttempts,
                backoff: $backoff,
            );
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
            callback: function (AMQPMessage $message) use (
                $channel,
                $dlq,
                $pipeline,
                $exchange,
                $retryRoutingKey,
                $maxAttempts,
                $backoff
            ): void {
                $this->processMessage(
                    message: $message,
                    channel: $channel,
                    dlq: $dlq,
                    pipeline: $pipeline,
                    exchange: $exchange,
                    retryRoutingKey: $retryRoutingKey,
                    maxAttempts: $maxAttempts,
                    backoff: $backoff,
                );
            }
        );

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();

        return self::SUCCESS;
    }

    /**
     * @param  array<int, int>  $backoff
     */
    private function processMessage(
        AMQPMessage $message,
        \PhpAmqpLib\Channel\AMQPChannel $channel,
        string $dlq,
        NewsProcessingPipeline $pipeline,
        string $exchange,
        string $retryRoutingKey,
        int $maxAttempts,
        array $backoff,
    ): void {
        $startedAt = microtime(true);

        try {
            $payload = json_decode($message->getBody(), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \RuntimeException('Invalid payload type.');
            }

            $attempt = $this->readAttempt($payload);
            $raw = $this->mapRawNews($payload);

            $pipeline->handle($raw);
            $message->ack();

            $this->line(sprintf('Processed fingerprint=%s', $raw->fingerprint));
            Log::info('news_processing_done', [
                'module' => 'intelligence',
                'fingerprint' => $raw->fingerprint,
                'attempt' => $attempt,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'status' => 'ok',
            ]);
        } catch (\Throwable $e) {
            $payload = json_decode($message->getBody(), true);
            $attempt = is_array($payload) ? $this->readAttempt($payload) : 1;
            $fingerprint = is_array($payload) ? (string) ($payload['fingerprint'] ?? '') : '';

            if (is_array($payload) && $attempt < $maxAttempts) {
                $nextAttempt = $attempt + 1;
                $delay = $this->retryDelaySeconds($nextAttempt, $backoff);

                $this->publishRetry(
                    channel: $channel,
                    payload: $payload,
                    failedMessage: $message,
                    exchange: $exchange,
                    retryRoutingKey: $retryRoutingKey,
                    nextAttempt: $nextAttempt,
                    delaySeconds: $delay,
                    error: $e,
                );
                $message->ack();

                $this->warn(sprintf(
                    'Retry scheduled fingerprint=%s attempt=%d/%d delay=%ds',
                    $fingerprint,
                    $nextAttempt,
                    $maxAttempts,
                    $delay,
                ));
                Log::warning('news_processing_retry_scheduled', [
                    'module' => 'intelligence',
                    'fingerprint' => $fingerprint,
                    'attempt' => $nextAttempt,
                    'max_attempts' => $maxAttempts,
                    'retry_delay_s' => $delay,
                    'error' => $e->getMessage(),
                    'status' => 'retry',
                ]);

                return;
            }

            $this->sendToDlq($channel, $dlq, $message, $e, $attempt);
            $message->ack();

            $this->error(sprintf('Failed message: %s', $e->getMessage()));
            Log::error('news_processing_failed_to_dlq', [
                'module' => 'intelligence',
                'fingerprint' => $fingerprint,
                'attempt' => $attempt,
                'max_attempts' => $maxAttempts,
                'error' => $e->getMessage(),
                'status' => 'failed',
            ]);
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
        \Throwable $e,
        int $attempt,
    ): void {
        $dlqPayload = json_encode([
            'failed_at' => now()->toIso8601String(),
            'attempt' => $attempt,
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function readAttempt(array $payload): int
    {
        $meta = $payload['_meta'] ?? [];
        if (! is_array($meta)) {
            return 1;
        }

        return max(1, (int) ($meta['attempt'] ?? 1));
    }

    /**
     * @param  array<int, int>  $backoff
     */
    private function retryDelaySeconds(int $nextAttempt, array $backoff): int
    {
        if ($nextAttempt <= 1) {
            return 0;
        }

        $index = min($nextAttempt - 2, count($backoff) - 1);

        return $backoff[$index];
    }

    /**
     * @return array<int, int>
     */
    private function normalizeBackoff(mixed $value): array
    {
        if (! is_array($value)) {
            return [5, 15, 60];
        }

        $backoff = [];
        foreach ($value as $item) {
            $delay = (int) $item;
            if ($delay >= 0) {
                $backoff[] = $delay;
            }
        }

        return $backoff === [] ? [5, 15, 60] : $backoff;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publishRetry(
        \PhpAmqpLib\Channel\AMQPChannel $channel,
        array $payload,
        AMQPMessage $failedMessage,
        string $exchange,
        string $retryRoutingKey,
        int $nextAttempt,
        int $delaySeconds,
        \Throwable $error,
    ): void {
        $payload['_meta'] = [
            'attempt' => $nextAttempt,
            'last_error' => $error->getMessage(),
            'last_failed_at' => now()->toIso8601String(),
        ];

        if ($delaySeconds > 0) {
            sleep($delaySeconds);
        }

        $retryMessage = new AMQPMessage(
            body: json_encode($payload, JSON_THROW_ON_ERROR),
            properties: [
                'content_type' => 'application/json',
                'delivery_mode' => 2,
                'message_id' => (string) ($failedMessage->get_properties()['message_id'] ?? uniqid('retry-', true)),
            ],
        );

        $channel->basic_publish($retryMessage, $exchange, $retryRoutingKey, true);
    }
}
