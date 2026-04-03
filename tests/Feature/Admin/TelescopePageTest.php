<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TelescopePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_telescope_page_with_embedded_requests_view(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->get('/admin/telescope');

        $response
            ->assertOk()
            ->assertSee('Open In New Tab')
            ->assertSee('/telescope/requests', escape: false)
            ->assertSee('iframe', escape: false);
    }
}
