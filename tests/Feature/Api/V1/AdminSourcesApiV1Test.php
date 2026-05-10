<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Database\Seeders\SourceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AdminSourcesApiV1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
        $this->seed(SourceSeeder::class);
    }

    public function test_v1_admin_sources_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/sources')
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated');
    }

    public function test_v1_admin_sources_supports_list_create_update_and_show(): void
    {
        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/sources')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
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

        $created = $this->actingAs($admin)
            ->postJson('/api/v1/admin/sources', [
                'name' => 'API Source',
                'url' => 'https://example.com/api-source.xml',
                'type' => 'rss',
                'language_default' => 'en',
                'cron_expression' => '*/5 * * * *',
                'is_active' => true,
                'error_streak' => 0,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'API Source');

        $sourceId = (int) $created->json('data.id');

        $this->actingAs($admin)
            ->getJson("/api/v1/admin/sources/{$sourceId}")
            ->assertOk()
            ->assertJsonPath('data.id', $sourceId);

        $this->actingAs($admin)
            ->patchJson("/api/v1/admin/sources/{$sourceId}", [
                'name' => 'API Source Updated',
                'is_active' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'API Source Updated')
            ->assertJsonPath('data.is_active', false);
    }
}
