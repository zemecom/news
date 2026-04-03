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

        $crawlerChannel = $this->createMock(AMQPChannel::class);
        $crawlerChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willReturn(['crawler_tasks', 4, 1]);
        $crawlerChannel->expects($this->once())->method('close');

        $intelligenceChannel = $this->createMock(AMQPChannel::class);
        $intelligenceChannel->expects($this->once())
            ->method('queue_declare')
            ->with('intelligence_tasks', true, true, false, false)
            ->willReturn(['intelligence_tasks', 9, 2]);
        $intelligenceChannel->expects($this->once())->method('close');

        $mediaChannel = $this->createMock(AMQPChannel::class);
        $mediaChannel->expects($this->once())
            ->method('queue_declare')
            ->with('media_tasks', true, true, false, false)
            ->willReturn(['media_tasks', 0, 0]);
        $mediaChannel->expects($this->once())->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->exactly(3))
            ->method('channel')
            ->willReturnOnConsecutiveCalls($crawlerChannel, $intelligenceChannel, $mediaChannel);

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
        $connection->expects($this->exactly(3))
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

    public function test_it_keeps_reading_other_queues_after_missing_queue_closes_the_first_channel(): void
    {
        $missingQueueChannel = $this->createMock(AMQPChannel::class);
        $missingQueueChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willThrowException(new RuntimeException("NOT_FOUND - no queue 'crawler_tasks' in vhost '/'"));
        $missingQueueChannel->expects($this->once())->method('close')
            ->willThrowException(new RuntimeException('Channel connection is closed'));

        $intelligenceChannel = $this->createMock(AMQPChannel::class);
        $intelligenceChannel->expects($this->once())
            ->method('queue_declare')
            ->with('intelligence_tasks', true, true, false, false)
            ->willReturn(['intelligence_tasks', 2, 1]);
        $intelligenceChannel->expects($this->once())->method('close');

        $mediaChannel = $this->createMock(AMQPChannel::class);
        $mediaChannel->expects($this->once())
            ->method('queue_declare')
            ->with('media_tasks', true, true, false, false)
            ->willReturn(['media_tasks', 0, 0]);
        $mediaChannel->expects($this->once())->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->exactly(3))
            ->method('channel')
            ->willReturnOnConsecutiveCalls($missingQueueChannel, $intelligenceChannel, $mediaChannel);

        $service = new QueueOverviewService($connection);
        $summaries = collect($service->getQueueSummaries())->keyBy('queue');

        $this->assertSame('unavailable', $summaries['crawler_tasks']['status']);
        $this->assertStringContainsString("NOT_FOUND - no queue 'crawler_tasks'", (string) $summaries['crawler_tasks']['error']);
        $this->assertSame('ok', $summaries['intelligence_tasks']['status']);
        $this->assertSame(2, $summaries['intelligence_tasks']['message_count']);
        $this->assertSame('ok', $summaries['media_tasks']['status']);
    }
}
