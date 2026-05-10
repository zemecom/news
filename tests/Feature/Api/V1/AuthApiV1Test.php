<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AuthApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent();
    }

    public function test_login_returns_authenticated_user_resource(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'abilities',
                ],
            ])
            ->assertJsonPath('data.email', 'admin@example.com')
            ->assertJsonPath('data.role', 'admin');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($user)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data.logged_out', true);

        $this->getJson('/api/v1/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_login_rejects_invalid_credentials_with_error_envelope(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'invalid_credentials');
    }
}
