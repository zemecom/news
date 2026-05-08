<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\QueuePreviewClient;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use Throwable;

final readonly class QueueManagementService
{
    public function __construct(
        private AMQPStreamConnection $connection,
        private QueuePreviewClient $managementApi,
    ) {}

    /**
     * @return list<array{
     *     queue:string,
     *     position:int,
     *     display_name:string,
     *     job_name:?string,
     *     job_uuid:?string,
     *     attempts:int,
     *     redelivered:bool,
     *     routing_key:?string,
     *     exchange:?string,
     *     payload_bytes:int,
     *     payload_preview:string
     * }>
     */
    public function previewQueue(string $queueName, int $limit = 10): array
    {
        $queueName = $this->guardQueueName($queueName);
        $messages = $this->managementApi->previewQueue($queueName, $limit);

        return array_map(
            fn (array $message, int $index): array => $this->normalizePreviewMessage($queueName, $message, $index + 1),
            $messages,
            array_keys($messages),
        );
    }

    /**
     * @return array{queue:string,before:?int,after:?int,processed:bool,exit_code:?int,output:?string}
     */
    public function processOneQueueJob(string $queueName): array
    {
        $queueName = $this->guardQueueName($queueName);
        $before = $this->messageCount($queueName);

        if ($before !== null && $before <= 0) {
            return [
                'queue' => $queueName,
                'before' => $before,
                'after' => $before,
                'processed' => false,
                'exit_code' => null,
                'output' => null,
            ];
        }

        $exitCode = Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => 1,
            '--queue' => $queueName,
            '--tries' => 3,
        ]);

        return [
            'queue' => $queueName,
            'before' => $before,
            'after' => $this->messageCount($queueName),
            'processed' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()) ?: null,
        ];
    }

    /**
     * @return array{queue:string,before:?int,after:?int,processed:bool,exit_code:?int,output:?string,max_jobs:int}
     */
    public function runUntilEmpty(string $queueName, int $maxJobs = 25): array
    {
        $queueName = $this->guardQueueName($queueName);
        $before = $this->messageCount($queueName);

        if ($before !== null && $before <= 0) {
            return [
                'queue' => $queueName,
                'before' => $before,
                'after' => $before,
                'processed' => false,
                'exit_code' => null,
                'output' => null,
                'max_jobs' => max(1, $maxJobs),
            ];
        }

        $resolvedMaxJobs = max(1, $maxJobs);
        $exitCode = Artisan::call('queue:work', [
            '--stop-when-empty' => true,
            '--max-jobs' => $resolvedMaxJobs,
            '--queue' => $queueName,
            '--tries' => 3,
        ]);

        return [
            'queue' => $queueName,
            'before' => $before,
            'after' => $this->messageCount($queueName),
            'processed' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim(Artisan::output()) ?: null,
            'max_jobs' => $resolvedMaxJobs,
        ];
    }

    public function purgeQueue(string $queueName): int
    {
        $queueName = $this->guardQueueName($queueName);
        $channel = $this->connection->channel();

        try {
            return (int) $channel->queue_purge($queueName);
        } finally {
            $this->closeChannel($channel);
        }
    }

    private function messageCount(string $queueName): ?int
    {
        $channel = $this->connection->channel();

        try {
            $queueState = $channel->queue_declare(
                queue: $queueName,
                passive: true,
                durable: true,
                exclusive: false,
                auto_delete: false,
            );

            return is_array($queueState) ? (int) ($queueState[1] ?? 0) : null;
        } finally {
            $this->closeChannel($channel);
        }
    }

    private function guardQueueName(string $queueName): string
    {
        if (! in_array($queueName, QueueOverviewService::QUEUES, true)) {
            throw new RuntimeException(sprintf('Queue "%s" is not allowed for admin operations.', $queueName));
        }

        return $queueName;
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     queue:string,
     *     position:int,
     *     display_name:string,
     *     job_name:?string,
     *     job_uuid:?string,
     *     attempts:int,
     *     redelivered:bool,
     *     routing_key:?string,
     *     exchange:?string,
     *     payload_bytes:int,
     *     payload_preview:string
     * }
     */
    private function normalizePreviewMessage(string $queueName, array $message, int $position): array
    {
        $payload = is_string($message['payload'] ?? null) ? $message['payload'] : '';
        /** @var array<string, mixed>|null $decodedPayload */
        $decodedPayload = json_decode($payload, true);

        $displayName = is_array($decodedPayload) && is_string($decodedPayload['displayName'] ?? null)
            ? (string) $decodedPayload['displayName']
            : 'Unknown job';
        $jobName = is_array($decodedPayload) && is_string($decodedPayload['job'] ?? null)
            ? (string) $decodedPayload['job']
            : null;
        $jobUuid = is_array($decodedPayload) && is_string($decodedPayload['uuid'] ?? null)
            ? (string) $decodedPayload['uuid']
            : null;
        $attempts = is_array($decodedPayload) ? (int) ($decodedPayload['attempts'] ?? 0) : 0;

        return [
            'queue' => $queueName,
            'position' => $position,
            'display_name' => class_basename($displayName),
            'job_name' => $jobName,
            'job_uuid' => $jobUuid,
            'attempts' => $attempts,
            'redelivered' => (bool) ($message['redelivered'] ?? false),
            'routing_key' => is_string($message['routing_key'] ?? null) ? $message['routing_key'] : null,
            'exchange' => is_string($message['exchange'] ?? null) ? $message['exchange'] : null,
            'payload_bytes' => (int) ($message['payload_bytes'] ?? strlen($payload)),
            'payload_preview' => Str::limit(preg_replace('/\s+/', ' ', trim($payload)) ?: 'n/a', 180),
        ];
    }

    private function closeChannel(AMQPChannel $channel): void
    {
        try {
            $channel->close();
        } catch (Throwable) {
        }
    }
}
