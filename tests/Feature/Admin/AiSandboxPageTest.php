<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AiSandboxPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_ai_sandbox_page(): void
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

        $response = $this->actingAs($admin)->get('/admin/ai-sandbox');

        $response
            ->assertOk()
            ->assertSee('AI Sandbox')
            ->assertSee('Sandbox Request')
            ->assertSee('Run AI Test')
            ->assertSee('Provider Snapshot')
            ->assertSee('Title')
            ->assertSee('Content');
    }
}
