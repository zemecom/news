<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Widgets\QueueOverviewWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Tests\TestCase;

final class QueueOverviewWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_overview_widget_renders_queue_health_cards(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'rabbitmq',
            'queue' => 'crawler_tasks',
            'payload' => json_encode(['displayName' => 'FetchSourceJob'], JSON_THROW_ON_ERROR),
            'exception' => "HTTP 500\nstack trace",
            'failed_at' => now(),
        ]);

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('queue_declare')
            ->willReturnCallback(static function (string $queue): array {
                return match ($queue) {
                    'crawler_tasks' => [$queue, 3, 1],
                    'intelligence_tasks' => [$queue, 5, 2],
                    default => [$queue, 0, 0],
                };
            });
        $channel->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->method('channel')->willReturn($channel);
        $this->app->instance(AMQPStreamConnection::class, $connection);

        $this->livewireAs($admin, QueueOverviewWidget::class)
            ->assertSee('Queue Overview')
            ->assertSee('crawler_tasks')
            ->assertSee('intelligence_tasks')
            ->assertSee('media_tasks')
            ->assertSee('Messages')
            ->assertSee('Consumers')
            ->assertSee('Failed');
    }
}
