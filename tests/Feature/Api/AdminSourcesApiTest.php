<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\SourceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Tests\TestCase;

final class AdminSourcesApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
        $this->seed(SourceSeeder::class);
    }

    public function test_admin_sources_requires_authentication(): void
    {
        $this->getJson('/api/admin/sources')
            ->assertUnauthorized();
    }

    public function test_admin_sources_forbids_regular_user(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'user@example.com')->firstOrFail();

        $this->actingAs($user)
            ->getJson('/api/admin/sources')
            ->assertForbidden();
    }

    public function test_admin_sources_is_accessible_for_admin(): void
    {
        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/sources')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'name',
                        'url',
                        'type',
                        'language_default',
                        'cron_expression',
                        'is_active',
                        'error_streak',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertCount(Source::query()->count(), $data);
        $this->assertTrue(collect($data)->contains(
            fn (array $source): bool => ($source['url'] ?? null) === 'https://t.me/toporlive'
                && ($source['type'] ?? null) === 'telegram'
        ));
    }
}
