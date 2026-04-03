<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\Operations;
use App\Models\User;
use App\Services\Contracts\QueuePreviewClient;
use App\Services\QueueManagementService;
use App\Services\QueueOverviewService;
use Filament\Livewire\Topbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

final class OperationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_operations_page_and_see_queue_and_ai_snapshots(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'default_reasoning_effort' => 'high',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_AUTHENTICATED,
            'account_email' => 'admin@example.com',
            'plan_type' => 'plus',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'rabbitmq',
            'queue' => 'intelligence_tasks',
            'payload' => json_encode(['displayName' => 'ProcessRawNewsListener'], JSON_THROW_ON_ERROR),
            'exception' => "Provider error\nstack trace",
            'failed_at' => now(),
        ]);

        $overviewChannel = $this->createMock(AMQPChannel::class);
        $overviewChannel->method('queue_declare')
            ->willReturnCallback(static function (string $queue): array {
                return match ($queue) {
                    'crawler_tasks' => [$queue, 1, 1],
                    'intelligence_tasks' => [$queue, 7, 2],
                    default => [$queue, 0, 0],
                };
            });
        $overviewChannel->method('close');

        $overviewConnection = $this->createMock(AMQPStreamConnection::class);
        $overviewConnection->method('channel')->willReturn($overviewChannel);
        $this->app->instance(QueueOverviewService::class, new QueueOverviewService($overviewConnection));

        $managementConnection = $this->createMock(AMQPStreamConnection::class);
        $queueManagement = new QueueManagementService($managementConnection, new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                TestCase::assertSame('crawler_tasks', $queueName);
                TestCase::assertSame(10, $limit);

                return [
                    [
                        'payload' => '{"displayName":"FetchSourceJob"}',
                        'payload_bytes' => 512,
                        'redelivered' => false,
                        'routing_key' => 'crawler_tasks',
                        'exchange' => 'news.jobs',
                    ],
                ];
            }
        });
        $this->app->instance(QueueManagementService::class, $queueManagement);

        $response = $this->actingAs($admin)->get('/admin/operations');

        $response
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Queues & Analysis Health')
            ->assertSee('crawler_tasks')
            ->assertSee('intelligence_tasks')
            ->assertSee('Recent Failures')
            ->assertSee('ProcessRawNewsListener')
            ->assertSee('FetchSourceJob')
            ->assertSee('Run 1 Job')
            ->assertSee('AI Provider')
            ->assertSee('ChatGPT Codex')
            ->assertSee('Refresh Provider Stats');
    }

    public function test_admin_can_run_queue_management_actions_from_operations_page(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $overviewChannel = $this->createMock(AMQPChannel::class);
        $overviewChannel->method('queue_declare')
            ->willReturnCallback(static fn (string $queue): array => [$queue, 0, 0]);
        $overviewChannel->method('close');

        $overviewConnection = $this->createMock(AMQPStreamConnection::class);
        $overviewConnection->method('channel')->willReturn($overviewChannel);
        $this->app->instance(QueueOverviewService::class, new QueueOverviewService($overviewConnection));

        $beforeChannel = $this->createMock(AMQPChannel::class);
        $beforeChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willReturn(['crawler_tasks', 2, 0]);
        $beforeChannel->expects($this->once())->method('close');

        $afterChannel = $this->createMock(AMQPChannel::class);
        $afterChannel->expects($this->once())
            ->method('queue_declare')
            ->with('crawler_tasks', true, true, false, false)
            ->willReturn(['crawler_tasks', 1, 0]);
        $afterChannel->expects($this->once())->method('close');

        $purgeChannel = $this->createMock(AMQPChannel::class);
        $purgeChannel->expects($this->once())
            ->method('queue_purge')
            ->with('crawler_tasks')
            ->willReturn(3);
        $purgeChannel->expects($this->once())->method('close');

        $managementConnection = $this->createMock(AMQPStreamConnection::class);
        $managementConnection->method('channel')
            ->willReturnOnConsecutiveCalls($beforeChannel, $afterChannel, $purgeChannel);

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

        $queueManagement = new QueueManagementService($managementConnection, new class implements QueuePreviewClient
        {
            public function previewQueue(string $queueName, int $limit = 10): array
            {
                return [];
            }
        });
        $this->app->instance(QueueManagementService::class, $queueManagement);

        $this->livewireAs($admin, Operations::class)
            ->call('processOneQueueJob', 'crawler_tasks')
            ->call('purgeQueue', 'crawler_tasks')
            ->assertHasNoErrors();
    }

    public function test_reload_roadrunner_action_runs_octane_reload_from_user_menu(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        Artisan::spy();
        Artisan::shouldReceive('call')
            ->once()
            ->with('reload')
            ->andReturn(0);
        Artisan::shouldReceive('output')
            ->zeroOrMoreTimes()
            ->andReturn('');

        $this->livewireAs($admin, Topbar::class)
            ->call('mountAction', 'reload_roadrunner')
            ->call('callMountedAction');
    }
}
