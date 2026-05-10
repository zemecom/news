<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Intelligence\Application\Listeners\ProcessRawNewsListener;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class AdminNewsApiV1Test extends TestCase
{
    use RefreshDatabase;

    public function test_v1_admin_news_supports_list_detail_and_ai_actions(): void
    {
        Queue::fake();

        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $first = $this->createNewsItem('fp-admin-1');
        $second = $this->createNewsItem('fp-admin-2');

        $this->actingAs($admin)
            ->getJson('/api/v1/admin/news')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'source',
                        'title',
                        'status',
                        'analysis',
                        'published_at',
                    ],
                ],
            ]);

        $this->actingAs($admin)
            ->getJson(sprintf('/api/v1/admin/news/%d', $first->getKey()))
            ->assertOk()
            ->assertJsonPath('data.id', (string) $first->getKey());

        $this->actingAs($admin)
            ->postJson(sprintf('/api/v1/admin/news/%d/reanalyze', $first->getKey()))
            ->assertOk()
            ->assertJsonPath('data.enqueued', 1);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/news/bulk/reanalyze', [
                'ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertOk()
            ->assertJsonPath('data.enqueued', 2);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/news/bulk/enrich-missing-ai', [
                'ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertOk()
            ->assertJsonStructure(['data' => ['enqueued']]);

        $this->actingAs($admin)
            ->postJson('/api/v1/admin/news/bulk/refresh-ai', [
                'ids' => [$first->getKey(), $second->getKey()],
            ])
            ->assertOk()
            ->assertJsonPath('data.enqueued', 2);

        Queue::assertPushedOn(
            'intelligence_tasks',
            CallQueuedListener::class,
            fn (CallQueuedListener $job): bool => $job->class === ProcessRawNewsListener::class
        );
    }

    private function createNewsItem(string $fingerprint): NewsItem
    {
        $source = Source::query()->firstOrCreate([
            'url' => 'https://example.com/rss.xml',
        ], [
            'name' => 'Tech Feed',
            'type' => 'rss',
            'language_default' => 'en',
            'is_active' => true,
            'error_streak' => 0,
        ]);

        /** @var NewsItem $item */
        $item = NewsItem::query()->create([
            'source_id' => $source->getKey(),
            'title_original' => 'Original title '.$fingerprint,
            'content_original' => 'Original content '.$fingerprint,
            'status' => NewsStatus::PUBLISHED->value,
            'raw_fingerprint' => $fingerprint,
            'published_at' => now()->subHour(),
            'source_metadata' => [
                'link' => 'https://example.com/news/'.$fingerprint,
                'language' => 'en',
                'external_id' => 'ext-'.$fingerprint,
            ],
        ]);

        return $item;
    }
}
