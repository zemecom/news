<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Intelligence\Application\Pipeline\Steps\ChatGptCodexEnrichmentStep;
use Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep;
use Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep;
use Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep;
use Modules\Intelligence\Application\Pipeline\Steps\SentimentStep;
use Modules\Intelligence\Application\Pipeline\Steps\TranslateStep;
use Modules\Intelligence\Application\Services\ActiveAiProviderResolver;
use Modules\Intelligence\Domain\Contracts\AiProviderStatusManager;
use Modules\Intelligence\Domain\Contracts\Classifier;
use Modules\Intelligence\Domain\Contracts\NewsAnalyzer;
use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;
use Modules\Intelligence\Domain\Contracts\Translator;
use Modules\Intelligence\Domain\DTO\NewsAnalysisResult;
use Modules\Intelligence\Infrastructure\LLM\HeuristicTranslator;
use Modules\Intelligence\Infrastructure\LLM\KeywordClassifier;
use Modules\Intelligence\Infrastructure\LLM\KeywordSentimentAnalyzer;
use Modules\Intelligence\Infrastructure\LLM\ObjectivelyTitleGenerator;
use Modules\Intelligence\Infrastructure\Persistence\Models\AiProviderAccount;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;
use Tests\TestCase;

final class StepAndHeuristicsTest extends TestCase
{
    use RefreshDatabase;

    public function test_translate_step_translates_non_russian_input_and_preserves_originals(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('Original content', 'ru', 'en')
            ->willReturn('Переведённый текст');

        $step = new TranslateStep($translator);
        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'content' => 'Original content',
            'language' => 'en',
            'metadata' => ['topic' => 'ai'],
        ]));

        $this->assertSame('Переведённый текст', $result->content);
        $this->assertSame('ru', $result->language);
        $this->assertSame('Original content', $result->metadata['original_content']);
        $this->assertSame('en', $result->metadata['original_language']);
        $this->assertSame('ai', $result->metadata['topic']);
    }

    public function test_chatgpt_codex_step_populates_metadata_and_translation(): void
    {
        config()->set('intelligence.provider', 'chatgpt_codex');

        AiProviderAccount::query()->create([
            'slug' => 'chatgpt-default',
            'provider' => AiProviderAccount::PROVIDER_CHATGPT_CODEX,
            'display_name' => 'ChatGPT Codex',
            'is_enabled' => true,
            'codex_home_subpath' => 'chatgpt-default',
            'default_model' => 'gpt-5.4-mini',
            'max_parallel_jobs' => 1,
            'auth_status' => AiProviderAccount::STATUS_AUTHENTICATED,
        ]);

        $analyzer = $this->createMock(NewsAnalyzer::class);
        $analyzer->expects($this->once())
            ->method('analyze')
            ->willReturn(new NewsAnalysisResult(
                translatedContent: 'Переведённый текст',
                generatedTitle: 'Нейтральный заголовок',
                category: 'IT',
                tags: ['ai', 'laravel'],
                sentiment: 5,
                analysisMetadata: [
                    'provider' => 'chatgpt_codex',
                    'model' => 'gpt-5.4-mini',
                ],
            ));

        $synchronizer = $this->createMock(AiProviderStatusManager::class);
        $synchronizer->expects($this->never())->method('markError');
        $synchronizer->expects($this->never())->method('markUsageLimited');
        $synchronizer->expects($this->never())->method('markNotAuthenticated');

        $step = new ChatGptCodexEnrichmentStep(
            analyzer: $analyzer,
            resolver: app(ActiveAiProviderResolver::class),
            statusSynchronizer: $synchronizer,
        );

        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'content' => 'Original content',
            'language' => 'en',
        ]));

        $this->assertSame('Переведённый текст', $result->content);
        $this->assertSame('ru', $result->language);
        $this->assertSame('IT', $result->metadata['category']);
        $this->assertSame(['ai', 'laravel'], $result->metadata['tags']);
        $this->assertSame(5, $result->metadata['sentiment']);
        $this->assertSame('Нейтральный заголовок', $result->metadata['title_generated']);
        $this->assertSame('Original content', $result->metadata['original_content']);
        $this->assertSame('en', $result->metadata['original_language']);
        $this->assertSame('chatgpt_codex', $result->metadata['analysis']['provider']);
    }

    public function test_translate_step_skips_russian_input(): void
    {
        $translator = $this->createMock(Translator::class);
        $translator->expects($this->never())
            ->method('translate');

        $step = new TranslateStep($translator);
        $input = $this->rawNews([
            'content' => 'Русский текст',
            'language' => 'ru',
        ]);

        /** @var RawNewsData $result */
        $result = $step->process($input);

        $this->assertSame('Русский текст', $result->content);
        $this->assertSame('ru', $result->language);
    }

    public function test_classify_step_merges_classification_metadata(): void
    {
        $classifier = $this->createMock(Classifier::class);
        $classifier->expects($this->once())
            ->method('classify')
            ->with('Some content')
            ->willReturn([
                'category' => 'IT',
                'tags' => ['it', 'laravel'],
            ]);

        $step = new ClassifyStep($classifier);
        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'content' => 'Some content',
            'metadata' => ['topic' => 'tech'],
        ]));

        $this->assertSame('tech', $result->metadata['topic']);
        $this->assertSame('IT', $result->metadata['category']);
        $this->assertSame(['it', 'laravel'], $result->metadata['tags']);
    }

    public function test_sentiment_step_adds_sentiment_score(): void
    {
        $sentiment = $this->createMock(SentimentAnalyzer::class);
        $sentiment->expects($this->once())
            ->method('score')
            ->with('Some content')
            ->willReturn(4);

        $step = new SentimentStep($sentiment);
        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'content' => 'Some content',
        ]));

        $this->assertSame(4, $result->metadata['sentiment']);
    }

    public function test_importance_step_marks_item_as_important_for_strong_tech_story(): void
    {
        $step = new ImportanceStep;
        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'metadata' => [
                'category' => 'IT',
                'sentiment' => 4,
            ],
        ]));

        $this->assertTrue($result->metadata['importance']);
    }

    public function test_importance_step_leaves_low_signal_story_unimportant(): void
    {
        $step = new ImportanceStep;
        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'metadata' => [
                'category' => 'Lifestyle',
                'sentiment' => 2,
            ],
        ]));

        $this->assertFalse($result->metadata['importance']);
    }

    public function test_finalize_step_converts_raw_news_into_enriched_news(): void
    {
        $step = new FinalizeStep;
        $result = $step->process($this->rawNews([
            'rawId' => 21,
            'content' => 'Переведённый текст',
            'metadata' => [
                'sentiment' => 4,
                'category' => 'IT',
                'tags' => ['it', 'laravel'],
                'importance' => true,
                'title_generated' => 'Нейтральный заголовок',
                'analysis' => [
                    'provider' => 'chatgpt_codex',
                ],
            ],
        ]));

        $this->assertInstanceOf(EnrichedNewsData::class, $result);
        $this->assertSame(21, $result->rawId);
        $this->assertSame('Нейтральный заголовок', $result->titleGenerated);
        $this->assertSame('Переведённый текст', $result->contentTranslated);
        $this->assertSame(4, $result->sentiment);
        $this->assertSame('IT', $result->category);
        $this->assertSame(['it', 'laravel'], $result->tags);
        $this->assertTrue($result->importance);
        $this->assertSame(NewsStatus::PUBLISHED, $result->status);
        $this->assertSame('chatgpt_codex', $result->analysisMetadata['provider']);
    }

    public function test_keyword_classifier_detects_keywords_and_defaults_to_empty_category(): void
    {
        $classifier = new KeywordClassifier;

        $it = $classifier->classify('Laravel and PHP announcements');
        $neutral = $classifier->classify('Completely neutral text');

        $this->assertSame('IT', $it['category']);
        $this->assertSame(['it', 'laravel', 'php'], $it['tags']);
        $this->assertSame('', $neutral['category']);
        $this->assertSame([], $neutral['tags']);
    }

    public function test_keyword_sentiment_analyzer_scores_positive_and_negative_words(): void
    {
        $analyzer = new KeywordSentimentAnalyzer;

        $this->assertGreaterThan(0, $analyzer->score('good growth success'));
        $this->assertLessThan(0, $analyzer->score('crisis loss decline'));
        $this->assertSame(0, $analyzer->score('neutral text'));
    }

    public function test_objectively_title_generator_prefers_clean_title_and_falls_back_to_content(): void
    {
        $generator = new ObjectivelyTitleGenerator;

        $clean = $generator->generate('<p>Hello world</p>', 'Шок!!! Срочно');
        $fallback = $generator->generate('', 'Regular title');
        $empty = $generator->generate('', 'Шок!!!');

        $this->assertSame('Hello world', $clean);
        $this->assertSame('Regular title', $fallback);
        $this->assertSame('Новость без заголовка', $empty);
    }

    public function test_anti_clickbait_step_generates_title_only_when_missing(): void
    {
        $generator = $this->createMock(\Modules\Intelligence\Domain\Contracts\TitleGenerator::class);
        $generator->expects($this->once())
            ->method('generate')
            ->with('Some content', 'Original title')
            ->willReturn('Generated title');

        $step = new \Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep($generator);

        /** @var RawNewsData $result */
        $result = $step->process($this->rawNews([
            'content' => 'Some content',
            'title' => 'Original title',
            'metadata' => [],
        ]));

        $this->assertSame('Generated title', $result->metadata['title_generated']);
    }

    public function test_heuristic_translator_returns_original_text(): void
    {
        $translator = new HeuristicTranslator;

        $this->assertSame('Original text', $translator->translate('Original text', 'ru', 'en'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function rawNews(array $overrides = []): RawNewsData
    {
        return new RawNewsData(
            sourceId: $overrides['sourceId'] ?? 1,
            externalId: $overrides['externalId'] ?? 'ext-1',
            title: $overrides['title'] ?? 'Original title',
            link: $overrides['link'] ?? 'https://example.com/news/1',
            content: $overrides['content'] ?? 'Original content',
            publishedAt: $overrides['publishedAt'] ?? CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
            language: $overrides['language'] ?? 'en',
            metadata: $overrides['metadata'] ?? [],
            imageUrl: $overrides['imageUrl'] ?? null,
            media: $overrides['media'] ?? [],
            fingerprint: $overrides['fingerprint'] ?? 'fp-1',
            rawId: $overrides['rawId'] ?? null,
        );
    }
}
