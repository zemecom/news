<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Pages\AdminSettings;
use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_page_is_accessible_and_persists_news_defaults(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertOk()
            ->assertSee('Admin Settings')
            ->assertSee('News Table');

        $this->livewireAs($admin, AdminSettings::class)
            ->set('data.news_auto_refresh_enabled', true)
            ->set('data.news_auto_refresh_interval_seconds', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('admin_settings', [
            'id' => 1,
            'news_auto_refresh_enabled' => true,
            'news_auto_refresh_interval_seconds' => 30,
        ]);

        $settings = AdminSetting::query()->find(1);

        $this->assertInstanceOf(AdminSetting::class, $settings);
        $this->assertTrue($settings->news_auto_refresh_enabled);
        $this->assertSame(30, $settings->news_auto_refresh_interval_seconds);
    }
}
