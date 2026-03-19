<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class NewsAdminPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_news_page_shows_news_rows_and_metadata_columns(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'admin',
        ]);

        $source = Source::query()->create([
            'name' => 'Tech Feed',
            'url' => 'https://example.com/rss',
            'type' => 'rss',
            'language_default' => 'en',
            'is_active' => true,
            'error_streak' => 0,
        ]);

        NewsItem::query()->create([
            'source_id' => $source->getKey(),
            'title_original' => 'OpenAI added Codex adapter to the pipeline',
            'content_original' => 'Original English content for the imported article.',
            'title_generated' => 'Codex adapter added to the import pipeline',
            'content_translated' => 'Переведённый текст новости для админки.',
            'sentiment_score' => 5,
            'tags' => ['ai', 'codex', 'pipeline'],
            'is_important' => true,
            'status' => NewsStatus::PUBLISHED->value,
            'source_metadata' => [
                'link' => 'https://example.com/openai-codex-adapter',
                'language' => 'en',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                    'model' => 'gpt-5.4-mini',
                    'reasoning_effort' => 'high',
                ],
                'analysis_runtime' => [
                    'status' => 'completed',
                    'attempt' => 1,
                ],
            ],
            'raw_fingerprint' => 'fingerprint-openai-codex-adapter',
            'published_at' => now()->subHour(),
            'image_url' => 'https://example.com/image.jpg',
            'media' => [
                ['url' => 'https://example.com/image.jpg', 'type' => 'image/jpeg'],
            ],
        ]);

        NewsItem::query()->create([
            'source_id' => $source->getKey(),
            'title_original' => 'Article waiting for the first AI pass',
            'content_original' => 'Fresh imported content without enrichment yet.',
            'status' => NewsStatus::PUBLISHED->value,
            'source_metadata' => [
                'link' => 'https://example.com/pending-ai-analysis',
                'language' => 'en',
            ],
            'raw_fingerprint' => 'fingerprint-pending-ai-analysis',
            'published_at' => now()->subMinutes(30),
        ]);

        $response = $this->actingAs($admin)->get('/admin/news');

        $response
            ->assertOk()
            ->assertSee('News')
            ->assertSee('Tech Feed')
            ->assertSee('OpenAI added Codex adapter to the pipeline')
            ->assertSee('Codex adapter added to the import pipeline')
            ->assertSee('Analysis Status')
            ->assertSee('Completed')
            ->assertSee('AI Analysis')
            ->assertSee('chatgpt_codex · gpt-5.4-mini')
            ->assertSee('AI Action')
            ->assertSee('Analyze Again')
            ->assertSee('Analyze')
            ->assertSee('chatgpt_codex')
            ->assertSee('gpt-5.4-mini')
            ->assertSee('Fingerprint')
            ->assertSee('Details')
            ->assertSee('Reanalyze selected')
            ->assertSee('Open Source');

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertLessThan(
            strpos($content, 'fi-pagination-records-per-page-select-ctn'),
            strpos($content, 'fi-pagination-items'),
        );
    }
}
