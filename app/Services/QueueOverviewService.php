<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Throwable;

final readonly class QueueOverviewService
{
    /**
     * @var list<string>
     */
    private const array QUEUES = [
        'crawler_tasks',
        'intelligence_tasks',
        'media_tasks',
    ];

    public function __construct(private AMQPStreamConnection $connection) {}

    /**
     * @return list<string>
     */
    public function queueNames(): array
    {
        return self::QUEUES;
    }

    /**
     * @return array<int, array{
     *     queue:string,
     *     status:string,
     *     message_count:?int,
     *     consumer_count:?int,
     *     worker_active:bool,
     *     failed_count:int,
     *     last_failed_at:?string,
     *     error:?string
     * }>
     */
    public function getQueueSummaries(): array
    {
        /** @var array<string, array{failed_count:int,last_failed_at:?string}> $failedStats */
        $failedStats = DB::table('failed_jobs')
            ->selectRaw('queue, COUNT(*) as failed_count, MAX(failed_at) as last_failed_at')
            ->whereIn('queue', self::QUEUES)
            ->groupBy('queue')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [
                (string) $row->queue => [
                    'failed_count' => (int) $row->failed_count,
                    'last_failed_at' => is_string($row->last_failed_at) ? $row->last_failed_at : null,
                ],
            ])
            ->all();

        $summaries = [];
        $channel = null;
        $connectionError = null;

        try {
            $channel = $this->connection->channel();
        } catch (Throwable $e) {
            $connectionError = $e->getMessage();
        }

        foreach (self::QUEUES as $queueName) {
            $stats = $failedStats[$queueName] ?? ['failed_count' => 0, 'last_failed_at' => null];

            if (! $channel instanceof AMQPChannel) {
                $summaries[] = [
                    'queue' => $queueName,
                    'status' => 'unavailable',
                    'message_count' => null,
                    'consumer_count' => null,
                    'worker_active' => false,
                    'failed_count' => (int) $stats['failed_count'],
                    'last_failed_at' => $stats['last_failed_at'],
                    'error' => $connectionError,
                ];

                continue;
            }

            try {
                $queueState = $channel->queue_declare(
                    queue: $queueName,
                    passive: true,
                    durable: true,
                    exclusive: false,
                    auto_delete: false,
                );
                $messageCount = is_array($queueState) ? (int) ($queueState[1] ?? 0) : 0;
                $consumerCount = is_array($queueState) ? (int) ($queueState[2] ?? 0) : 0;

                $summaries[] = [
                    'queue' => $queueName,
                    'status' => 'ok',
                    'message_count' => $messageCount,
                    'consumer_count' => $consumerCount,
                    'worker_active' => $consumerCount > 0,
                    'failed_count' => (int) $stats['failed_count'],
                    'last_failed_at' => $stats['last_failed_at'],
                    'error' => null,
                ];
            } catch (Throwable $e) {
                $summaries[] = [
                    'queue' => $queueName,
                    'status' => 'unavailable',
                    'message_count' => null,
                    'consumer_count' => null,
                    'worker_active' => false,
                    'failed_count' => (int) $stats['failed_count'],
                    'last_failed_at' => $stats['last_failed_at'],
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($channel instanceof AMQPChannel) {
            try {
                $channel->close();
            } catch (Throwable) {
            }
        }

        return $summaries;
    }

    /**
     * @return array<int, array{
     *     queue:string,
     *     display_name:string,
     *     exception_summary:string,
     *     failed_at:string
     * }>
     */
    public function getRecentFailedJobs(int $limit = 10): array
    {
        /** @var array<int, object{queue:string,payload:string,exception:string,failed_at:string}> $rows */
        $rows = DB::table('failed_jobs')
            ->select(['queue', 'payload', 'exception', 'failed_at'])
            ->whereIn('queue', self::QUEUES)
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get()
            ->all();

        return array_map(function (object $row): array {
            /** @var array<string, mixed>|null $payload */
            $payload = json_decode($row->payload, true);
            $exceptionSummary = strtok($row->exception, "\n");

            return [
                'queue' => $row->queue,
                'display_name' => is_array($payload) && is_string($payload['displayName'] ?? null)
                    ? $payload['displayName']
                    : 'Unknown job',
                'exception_summary' => is_string($exceptionSummary)
                    ? $exceptionSummary
                    : 'Unknown exception',
                'failed_at' => $row->failed_at,
            ];
        }, $rows);
    }
}
