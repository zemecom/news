<?php

declare(strict_types=1);

namespace Tests\Unit\App;

use App\Services\Contracts\QueuePreviewClient;
use App\Services\QueueManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

final class QueueManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_formats_preview_jobs_from_management_api_payload(): void
    {
        /** @var AMQPStreamConnection&MockObject $connection */
        $connection = $this->createMock(AMQPStreamConnection::class);

        $managementApi = new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                TestCase::assertSame('crawler_tasks', $queueName);
                TestCase::assertSame(10, $limit);

                return [
                    [
                        'payload' => json_encode([
                            'uuid' => 'job-123',
                            'displayName' => 'App\\Jobs\\FetchSourceJob',
                            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                            'attempts' => 2,
                        ], JSON_THROW_ON_ERROR),
                        'payload_bytes' => 420,
                        'redelivered' => true,
                        'routing_key' => 'crawler_tasks',
                        'exchange' => 'news.jobs',
                    ],
                ];
            }
        };

        $service = new QueueManagementService($connection, $managementApi);
        $jobs = $service->previewQueue('crawler_tasks');

        $this->assertCount(1, $jobs);
        $this->assertSame('crawler_tasks', $jobs[0]['queue']);
        $this->assertSame(1, $jobs[0]['position']);
        $this->assertSame('FetchSourceJob', $jobs[0]['display_name']);
        $this->assertSame('job-123', $jobs[0]['job_uuid']);
        $this->assertSame(2, $jobs[0]['attempts']);
        $this->assertTrue($jobs[0]['redelivered']);
    }

    public function test_it_purges_queue_messages(): void
    {
        /** @var AMQPChannel&MockObject $channel */
        $channel = $this->createMock(AMQPChannel::class);
        $channel->expects($this->once())
            ->method('queue_purge')
            ->with('media_tasks')
            ->willReturn(4);
        $channel->expects($this->once())->method('close');

        /** @var AMQPStreamConnection&MockObject $connection */
        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->once())
            ->method('channel')
            ->willReturn($channel);

        $managementApi = new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        };

        $service = new QueueManagementService($connection, $managementApi);

        $this->assertSame(4, $service->purgeQueue('media_tasks'));
    }

    public function test_it_runs_one_step_queue_work_for_selected_queue(): void
    {
        /** @var AMQPChannel&MockObject $beforeChannel */
        $beforeChannel = $this->createMock(AMQPChannel::class);
        $beforeChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willReturn(['crawler_tasks', 1, 0]);
        $beforeChannel->expects($this->once())->method('close');

        /** @var AMQPChannel&MockObject $afterChannel */
        $afterChannel = $this->createMock(AMQPChannel::class);
        $afterChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willReturn(['crawler_tasks', 0, 0]);
        $afterChannel->expects($this->once())->method('close');

        /** @var AMQPStreamConnection&MockObject $connection */
        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->expects($this->exactly(2))
            ->method('channel')
            ->willReturnOnConsecutiveCalls($beforeChannel, $afterChannel);

        Artisan::spy();
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:work', [
                '--stop-when-empty' => true,
                '--max-jobs' => 1,
                '--queue' => 'crawler_tasks',
                '--tries' => 3,
            ])
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->once()
            ->andReturn('');

        $managementApi = new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        };
        $service = new QueueManagementService($connection, $managementApi);

        $result = $service->processOneQueueJob('crawler_tasks');

        $this->assertTrue($result['processed']);
        $this->assertSame(1, $result['before']);
        $this->assertSame(0, $result['after']);
    }
}
