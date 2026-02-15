<?php

declare(strict_types=1);

namespace Tests\Feature\Acceptance;

use App\Models\User;
use Database\Seeders\NewsItemSeeder;
use Database\Seeders\SourceSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('acceptance')]
final class NewsFlowAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(UserSeeder::class);
        $this->seed(SourceSeeder::class);
        $this->seed(NewsItemSeeder::class);
    }

    public function test_user_can_open_feed_and_news_details(): void
    {
        $feed = $this->getJson('/api/news?category=laravel&important=0&sentiment_min=3&sentiment_max=5&per_page=1');
        $feed->assertOk();

        $items = $feed->json('data');
        $this->assertIsArray($items);
        $this->assertCount(1, $items);

        /** @var array<string, mixed> $item */
        $item = $items[0];
        $id = (string) ($item['id'] ?? '');
        $this->assertNotSame('', $id);
        $this->assertSame('published', $item['status'] ?? null);
        $this->assertIsArray($item['media'] ?? null);

        $details = $this->getJson("/api/news/{$id}");
        $details
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'published');
    }

    public function test_admin_route_is_available_only_for_admin(): void
    {
        $this->getJson('/api/admin/sources')->assertUnauthorized();

        /** @var User $regularUser */
        $regularUser = User::query()->where('email', 'user@example.com')->firstOrFail();
        $this->actingAs($regularUser)->getJson('/api/admin/sources')->assertForbidden();

        /** @var User $admin */
        $admin = User::query()->where('email', 'admin@example.com')->firstOrFail();
        $response = $this->actingAs($admin)
            ->getJson('/api/admin/sources')
            ->assertOk()
            ->assertJsonCount(Source::query()->count(), 'data');

        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertTrue(collect($data)->contains(
            fn (array $source): bool => ($source['url'] ?? null) === '@toporlive'
                && ($source['type'] ?? null) === 'telegram'
        ));
    }
}
