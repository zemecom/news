<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Widgets\AiProviderStatusWidget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AiProviderDashboardWidgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_provider_widget_renders_dashboard_status_content(): void
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
            'rate_limit_snapshot' => [
                'primary' => [
                    'usedPercent' => 12,
                    'resetsAt' => now()->addHour()->timestamp,
                ],
                'secondary' => [
                    'usedPercent' => 34,
                    'resetsAt' => now()->addDays(2)->timestamp,
                    'windowDurationMins' => 10_080,
                ],
            ],
            'last_status_checked_at' => now(),
        ]);

        $this->livewireAs($admin, AiProviderStatusWidget::class)
            ->assertSee('AI Provider')
            ->assertSee('ChatGPT Codex')
            ->assertSee('Authenticated')
            ->assertSee('Refresh Provider Stats')
            ->assertSee('Runtime Snapshot')
            ->assertSee('Used')
            ->assertSee('12%')
            ->assertSee('Week')
            ->assertSee('34%');
    }

    public function test_refresh_provider_statistics_action_runs_from_widget(): void
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
            'last_status_checked_at' => now(),
        ]);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->once())
            ->method('sync')
            ->with($this->callback(static function (AiProviderProfile $profile): bool {
                return $profile->provider === AiProviderAccount::PROVIDER_CHATGPT_CODEX
                    && $profile->slug === 'chatgpt-default';
            }));
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->never())->method('markNotAuthenticated');
        $statusManager->expects($this->never())->method('markError');

        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $this->livewireAs($admin, AiProviderStatusWidget::class)
            ->call('refreshProviderStatistics');
    }

    public function test_widget_auto_refreshes_provider_stats_when_snapshot_is_stale(): void
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
            'last_status_checked_at' => now()->subMinutes(6),
        ]);

        $statusManager = $this->createMock(AiProviderStatusManager::class);
        $statusManager->expects($this->once())
            ->method('sync')
            ->with($this->callback(static function (AiProviderProfile $profile): bool {
                return $profile->provider === AiProviderAccount::PROVIDER_CHATGPT_CODEX
                    && $profile->slug === 'chatgpt-default';
            }));
        $statusManager->expects($this->never())->method('markUsageLimited');
        $statusManager->expects($this->never())->method('markNotAuthenticated');
        $statusManager->expects($this->never())->method('markError');

        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $this->livewireAs($admin, AiProviderStatusWidget::class)
            ->assertSee('AI Provider')
            ->assertSee('Updating usage data...')
            ->assertSee('Updating weekly data...');
    }
}
