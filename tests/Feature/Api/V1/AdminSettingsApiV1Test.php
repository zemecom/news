<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSettingsApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_v1_admin_settings_are_readable_and_updatable_for_admin(): void
    {
        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'news_auto_refresh_enabled',
                    'news_auto_refresh_interval_seconds',
                    'news_auto_refresh_selection_options',
                ],
            ]);

        $this->actingAs($admin)
            ->putJson('/api/v1/admin/settings', [
                'news_auto_refresh_enabled' => true,
                'news_auto_refresh_interval_seconds' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.news_auto_refresh_enabled', true)
            ->assertJsonPath('data.news_auto_refresh_interval_seconds', 30);
    }
}
