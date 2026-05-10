<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Domain\Contracts\AiProviderAuthManager;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\DTO\AiProviderProfile;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AdminAiProviderAccountsApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_v1_admin_ai_provider_accounts_list_update_and_actions_work(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $account = AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'default_reasoning_effort' => 'high',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $statusManager = new class implements AiProviderStatusManager
        {
            public int $syncCalls = 0;

            public function sync(AiProviderProfile $account): void
            {
                $this->syncCalls++;
            }

            public function markUsageLimited(AiProviderProfile $account, ?array $snapshot = null, ?string $message = null): void {}

            public function markNotAuthenticated(AiProviderProfile $account): void {}

            public function markError(AiProviderProfile $account, string $message): void {}
        };
        $this->app->instance(AiProviderStatusManager::class, $statusManager);

        $authManager = new class implements AiProviderAuthManager
        {
            public int $startLoginCalls = 0;

            public int $cancelLoginCalls = 0;

            public int $logoutCalls = 0;

            public function startLogin(AiProviderProfile $account): void
            {
                $this->startLoginCalls++;
            }

            public function cancelLogin(AiProviderProfile $account): void
            {
                $this->cancelLoginCalls++;
            }

            public function logout(AiProviderProfile $account): void
            {
                $this->logoutCalls++;
            }
        };
        $this->app->instance(AiProviderAuthManager::class, $authManager);

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/ai-provider-accounts')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'slug',
                        'provider',
                        'display_name',
                        'auth_status',
                        'default_model',
                        'default_reasoning_effort',
                        'max_parallel_jobs',
                    ],
                ],
            ]);

        $this->actingAs($admin)
            ->patchJson(sprintf('/api/v1/admin/ai-provider-accounts/%d', $account->getKey()), [
                'display_name' => 'ChatGPT Codex Updated',
                'default_model' => 'gpt-5.4',
                'default_reasoning_effort' => 'medium',
                'max_parallel_jobs' => 2,
                'is_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.display_name', 'ChatGPT Codex Updated')
            ->assertJsonPath('data.default_model', 'gpt-5.4')
            ->assertJsonPath('data.max_parallel_jobs', 2);

        $this->actingAs($admin)
            ->postJson(sprintf('/api/v1/admin/ai-provider-accounts/%d/sync', $account->getKey()))
            ->assertOk()
            ->assertJsonPath('data.status', 'synced');

        $this->actingAs($admin)
            ->postJson(sprintf('/api/v1/admin/ai-provider-accounts/%d/login', $account->getKey()))
            ->assertOk()
            ->assertJsonPath('data.status', 'login_started');

        $this->actingAs($admin)
            ->postJson(sprintf('/api/v1/admin/ai-provider-accounts/%d/cancel-login', $account->getKey()))
            ->assertOk()
            ->assertJsonPath('data.status', 'login_canceled');

        $this->actingAs($admin)
            ->postJson(sprintf('/api/v1/admin/ai-provider-accounts/%d/logout', $account->getKey()))
            ->assertOk()
            ->assertJsonPath('data.status', 'logged_out');

        $this->assertSame(1, $statusManager->syncCalls);
        $this->assertSame(1, $authManager->startLoginCalls);
        $this->assertSame(1, $authManager->cancelLoginCalls);
        $this->assertSame(1, $authManager->logoutCalls);
    }
}
