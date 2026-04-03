<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Filament\Resources\News\Pages\ListNews;
use App\Models\AdminSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class NewsAutoRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_page_uses_admin_default_auto_refresh_interval(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        AdminSetting::query()->create([
            'id' => 1,
            'news_auto_refresh_enabled' => true,
            'news_auto_refresh_interval_seconds' => 15,
        ]);

        $component = $this->livewireAs($admin, ListNews::class)
            ->call('loadTable');

        $this->assertSame('default', $component->instance()->newsAutoRefreshSelection);
        $this->assertSame('15s', $component->instance()->newsAutoRefreshInterval());
        $component->assertSeeHtml('wire:poll.15s');
    }

    public function test_news_page_allows_session_override_for_auto_refresh_interval(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        AdminSetting::query()->create([
            'id' => 1,
            'news_auto_refresh_enabled' => false,
            'news_auto_refresh_interval_seconds' => 15,
        ]);

        $component = $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            ->call('setNewsAutoRefreshSelection', '1s')
            ->assertSet('newsAutoRefreshSelection', '1s');

        $this->assertSame('1s', session('admin.news.auto_refresh_selection'));
        $this->assertSame('1s', $component->instance()->newsAutoRefreshInterval());
        $component->assertSeeHtml('wire:poll.1s');
    }

    public function test_news_page_can_disable_auto_refresh_for_current_session(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        AdminSetting::query()->create([
            'id' => 1,
            'news_auto_refresh_enabled' => true,
            'news_auto_refresh_interval_seconds' => 15,
        ]);

        $component = $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            ->call('setNewsAutoRefreshSelection', 'off')
            ->assertSet('newsAutoRefreshSelection', 'off');

        $this->assertNull($component->instance()->newsAutoRefreshInterval());
        $component->assertDontSeeHtml('wire:poll.');
    }

    public function test_news_page_refresh_method_reloads_changed_record_state(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        AdminSetting::query()->create([
            'id' => 1,
            'news_auto_refresh_enabled' => true,
            'news_auto_refresh_interval_seconds' => 1,
        ]);

        $source = Source::query()->create([
            'name' => 'Tech Feed',
            'url' => 'https://example.com/rss',
            'type' => 'rss',
            'language_default' => 'en',
            'is_active' => true,
            'error_streak' => 0,
        ]);

        /** @var NewsItem $news */
        $news = NewsItem::query()->create([
            'source_id' => $source->getKey(),
            'title_original' => 'Fresh item without analysis',
            'content_original' => 'Original content',
            'status' => NewsStatus::PUBLISHED->value,
            'source_metadata' => [
                'link' => 'https://example.com/news/fresh-item',
                'language' => 'en',
            ],
            'raw_fingerprint' => 'fingerprint-fresh-item',
            'published_at' => now()->subMinute(),
        ]);

        $component = $this->livewireAs($admin, ListNews::class)
            ->call('loadTable')
            ->assertSee('Not Analyzed')
            ->assertSeeHtml('wire:poll.1s="refreshNewsPage"');

        $news->update([
            'source_metadata' => [
                'link' => 'https://example.com/news/fresh-item',
                'language' => 'en',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                    'model' => 'gpt-5.4-mini',
                ],
                'analysis_runtime' => [
                    'status' => 'completed',
                    'attempt' => 1,
                ],
            ],
        ]);

        $component
            ->call('refreshNewsPage')
            ->assertSee('Completed')
            ->assertSee('chatgpt_codex · gpt-5.4-mini');
    }
}
