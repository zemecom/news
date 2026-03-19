<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Filament\Livewire\Topbar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
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

        $channel = $this->createMock(AMQPChannel::class);
        $channel->method('queue_declare')
            ->willReturnCallback(static function (string $queue): array {
                return match ($queue) {
                    'crawler_tasks' => [$queue, 1, 1],
                    'intelligence_tasks' => [$queue, 7, 2],
                    default => [$queue, 0, 0],
                };
            });
        $channel->method('close');

        $connection = $this->createMock(AMQPStreamConnection::class);
        $connection->method('channel')->willReturn($channel);
        $this->app->instance(AMQPStreamConnection::class, $connection);

        $response = $this->actingAs($admin)->get('/admin/operations');

        $response
            ->assertOk()
            ->assertSee('Operations')
            ->assertSee('Queue Overview')
            ->assertSee('crawler_tasks')
            ->assertSee('intelligence_tasks')
            ->assertSee('Recent Failures')
            ->assertSee('ProcessRawNewsListener')
            ->assertSee('AI Provider')
            ->assertSee('ChatGPT Codex')
            ->assertSee('Refresh Provider Stats');
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

        Livewire::actingAs($admin)
            ->test(Topbar::class)
            ->callAction('reload_roadrunner');
    }
}
