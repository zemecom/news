<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\QueueOverviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use RuntimeException;
use Tests\TestCase;

final class QueueOverviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_queue_summaries_and_recent_failed_jobs(): void
    {
        DB::table('failed_jobs')->insert([
            [
                'uuid' => Str::uuid()->toString(),
                'connection' => 'rabbitmq',
                'queue' => 'intelligence_tasks',
                'payload' => json_encode(['displayName' => 'ProcessRawNewsListener'], JSON_THROW_ON_ERROR),
                'exception' => "Rate limit reached\nstack trace",
                'failed_at' => now()->subMinute(),
            ],
            [
                'uuid' => Str::uuid()->toString(),
                'connection' => 'rabbitmq',
                'queue' => 'crawler_tasks',
                'payload' => json_encode(['displayName' => 'FetchSourceJob'], JSON_THROW_ON_ERROR),
                'exception' => "HTTP 500\nstack trace",
                'failed_at' => now()->subMinutes(2),
            ],
        ]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->exactly(3))
            ->method('queue_declare')
            ->willReturnCallback(static function (string $queue): array {
                return match ($queue) {
                    'crawler_tasks' => [$queue, 4, 1],
                    'intelligence_tasks' => [$queue, 9, 2],
                    'media_tasks' => [$queue, 0, 0],
                    default => [$queue, 0, 0],
                };
            });
        $channel->expects($this->once())->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $service = new QueueOverviewService($connection);
        $summaries = collect($service->getQueueSummaries())->keyBy('queue');
        $recentFailures = $service->getRecentFailedJobs();

        $this->assertSame('ok', $summaries['crawler_tasks']['status']);
        $this->assertSame(4, $summaries['crawler_tasks']['message_count']);
        $this->assertTrue($summaries['crawler_tasks']['worker_active']);
        $this->assertSame(1, $summaries['crawler_tasks']['failed_count']);

        $this->assertSame('ok', $summaries['intelligence_tasks']['status']);
        $this->assertSame(9, $summaries['intelligence_tasks']['message_count']);
        $this->assertSame(2, $summaries['intelligence_tasks']['consumer_count']);
        $this->assertSame(1, $summaries['intelligence_tasks']['failed_count']);

        $this->assertSame('ok', $summaries['media_tasks']['status']);
        $this->assertSame(0, $summaries['media_tasks']['message_count']);
        $this->assertFalse($summaries['media_tasks']['worker_active']);

        $this->assertCount(2, $recentFailures);
        $this->assertSame('intelligence_tasks', $recentFailures[0]['queue']);
        $this->assertSame('ProcessRawNewsListener', $recentFailures[0]['display_name']);
        $this->assertSame('Rate limit reached', $recentFailures[0]['exception_summary']);
    }

    public function test_it_marks_queues_as_unavailable_when_rabbitmq_connection_fails(): void
    {
        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->once())
            ->method('channel')
            ->willThrowException(new RuntimeException('RabbitMQ is unavailable'));

        $service = new QueueOverviewService($connection);
        $summaries = $service->getQueueSummaries();

        $this->assertCount(3, $summaries);
        $this->assertSame('unavailable', $summaries[0]['status']);
        $this->assertSame('RabbitMQ is unavailable', $summaries[0]['error']);
        $this->assertNull($summaries[0]['message_count']);
        $this->assertFalse($summaries[0]['worker_active']);
    }
}
