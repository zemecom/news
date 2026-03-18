<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Tests\TestCase;

final class AiProviderAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_ai_provider_page_is_accessible_for_admin(): void
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
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $response = $this->actingAs($admin)->get('/admin/ai-provider-accounts');

        $response
            ->assertOk()
            ->assertSee('ChatGPT Codex')
            ->assertSee('AI Providers')
            ->assertSee('Authenticate')
            ->assertSee('Refresh Statistics')
            ->assertSee('Reasoning')
            ->assertSee('Week Used %')
            ->assertSee('Week Reset At');
    }

    public function test_admin_ai_provider_edit_page_shows_available_model_choices_for_plus_plan(): void
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
            'plan_type' => 'plus',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $response = $this->actingAs($admin)->get("/admin/ai-provider-accounts/{$account->getKey()}/edit");

        $response
            ->assertOk()
            ->assertSee('Default model')
            ->assertSee('Reasoning effort')
            ->assertSee('GPT-5.4')
            ->assertSee('GPT-5.4-Mini')
            ->assertSee('GPT-5.3-Codex')
            ->assertSee('GPT-5.2-Codex')
            ->assertSee('GPT-5.2')
            ->assertSee('GPT-5.1-Codex-Max')
            ->assertSee('GPT-5.1-Codex-Mini')
            ->assertDontSee('GPT-5.3-Codex-Spark (ChatGPT Pro preview)')
            ->assertDontSee('value="gpt-5.1-codex"', false)
            ->assertDontSee('value="gpt-5-codex"', false)
            ->assertDontSee('value="gpt-5-codex-mini"', false)
            ->assertDontSee('value="gpt-5"', false)
            ->assertSee('Use model default')
            ->assertSee('Low')
            ->assertSee('Medium')
            ->assertSee('High')
            ->assertSee('XHigh')
            ->assertDontSee('Minimal');
    }

    public function test_admin_ai_provider_edit_page_shows_gpt_5_1_codex_mini_reasoning_levels_only(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $account = AiProviderAccount::query()->create([
            'slug' => 'chatgpt-mini',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex Mini',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-mini',
            'default_model' => 'gpt-5.1-codex-mini',
            'default_reasoning_effort' => 'medium',
            'plan_type' => 'plus',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $response = $this->actingAs($admin)->get("/admin/ai-provider-accounts/{$account->getKey()}/edit");

        $response
            ->assertOk()
            ->assertSee('Reasoning effort')
            ->assertSee('Use model default')
            ->assertSee('Medium')
            ->assertSee('High')
            ->assertDontSee('Minimal')
            ->assertDontSee('Low')
            ->assertDontSee('XHigh');
    }

    public function test_admin_ai_provider_edit_page_shows_gpt_5_1_codex_max_reasoning_levels(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $account = AiProviderAccount::query()->create([
            'slug' => 'chatgpt-max',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex Max',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-max',
            'default_model' => 'gpt-5.1-codex-max',
            'default_reasoning_effort' => 'high',
            'plan_type' => 'plus',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $response = $this->actingAs($admin)->get("/admin/ai-provider-accounts/{$account->getKey()}/edit");

        $response
            ->assertOk()
            ->assertSee('Reasoning effort')
            ->assertSee('Use model default')
            ->assertSee('Low')
            ->assertSee('Medium')
            ->assertSee('High')
            ->assertSee('XHigh')
            ->assertDontSee('Minimal');
    }

    public function test_admin_ai_provider_edit_page_shows_pro_only_model_and_model_specific_reasoning_levels(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $account = AiProviderAccount::query()->create([
            'slug' => 'chatgpt-pro',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex Pro',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-pro',
            'default_model' => 'gpt-5.4',
            'default_reasoning_effort' => 'high',
            'plan_type' => 'pro',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_NOT_AUTHENTICATED,
        ]);

        $response = $this->actingAs($admin)->get("/admin/ai-provider-accounts/{$account->getKey()}/edit");

        $response
            ->assertOk()
            ->assertSee('GPT-5.4')
            ->assertSee('GPT-5.4-Mini')
            ->assertSee('GPT-5.3-Codex')
            ->assertSee('GPT-5.3-Codex-Spark (ChatGPT Pro preview)')
            ->assertSee('GPT-5.1-Codex-Mini')
            ->assertSee('Low')
            ->assertSee('Medium')
            ->assertSee('High')
            ->assertSee('XHigh')
            ->assertDontSee('Minimal');
    }
}
