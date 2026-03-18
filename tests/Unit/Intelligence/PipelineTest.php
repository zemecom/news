<?php

declare(strict_types=1);

namespace Tests\Unit\Intelligence;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;
use Modules\Intelligence\Application\Listeners\ProcessRawNewsListener;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep;
use Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep;
use Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep;
use Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep;
use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep;
use Modules\Intelligence\Application\Pipeline\Steps\SentimentStep;
use Modules\Intelligence\Application\Pipeline\Steps\TranslateStep;
use Modules\Intelligence\Domain\Contracts\Classifier;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher;
use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;
use Modules\Intelligence\Domain\Contracts\Translator;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;
use Modules\Shared\Domain\Events\NewsEnriched;
use Modules\Shared\Domain\Events\RawNewsCreated;
use Tests\TestCase;

final class PipelineTest extends TestCase
{
    public function test_pipeline_processes_raw_news_and_publishes_enriched_result(): void
    {
        Event::fake();

        $translator = $this->createMock(Translator::class);
        $translator->expects($this->once())
            ->method('translate')
            ->with('Original content about Laravel growth', 'ru', 'en')
            ->willReturn('Переведённый текст');

        $classifier = $this->createMock(Classifier::class);
        $classifier->expects($this->once())
            ->method('classify')
            ->with('Переведённый текст')
            ->willReturn([
                'category' => 'IT',
                'tags' => ['it', 'laravel'],
            ]);

        $sentiment = $this->createMock(SentimentAnalyzer::class);
        $sentiment->expects($this->once())
            ->method('score')
            ->with('Переведённый текст')
            ->willReturn(5);

        $newsStore = $this->createMock(NewsStore::class);
        $newsStore->expects($this->once())
            ->method('existsByFingerprint')
            ->with('fp-1')
            ->willReturn(false);
        $newsStore->expects($this->once())
            ->method('storeRaw')
            ->with($this->callback(static function (RawNewsData $raw): bool {
                return $raw->fingerprint === 'fp-1';
            }))
            ->willReturn(42);
        $newsStore->expects($this->once())
            ->method('storeEnriched')
            ->with($this->callback(static function (EnrichedNewsData $enriched): bool {
                return $enriched->rawId === 42
                    && $enriched->contentTranslated === 'Переведённый текст'
                    && $enriched->category === 'IT'
                    && $enriched->tags === ['it', 'laravel']
                    && $enriched->importance === true
                    && $enriched->status === NewsStatus::PUBLISHED;
            }));

        $publisher = $this->createMock(EnrichedPublisher::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with($this->callback(static function (EnrichedNewsData $enriched): bool {
                return $enriched->rawId === 42
                    && $enriched->status === NewsStatus::PUBLISHED;
            }));

        $pipeline = new NewsProcessingPipeline(
            [
                new DeduplicateStep($newsStore),
                new TranslateStep($translator),
                new ClassifyStep($classifier),
                new SentimentStep($sentiment),
                new ImportanceStep,
                new FinalizeStep,
            ],
            $publisher,
            $newsStore,
        );

        $pipeline->handle($this->rawNews());

        Event::assertDispatched(NewsEnriched::class, static fn (NewsEnriched $event): bool => $event->rawId === 42);
    }

    public function test_pipeline_skips_duplicate_raw_news_without_dispatching_events(): void
    {
        Event::fake();

        $newsStore = $this->createMock(NewsStore::class);
        $newsStore->expects($this->once())
            ->method('existsByFingerprint')
            ->with('fp-1')
            ->willReturn(true);
        $newsStore->expects($this->never())
            ->method('storeRaw');
        $newsStore->expects($this->never())
            ->method('storeEnriched');

        $publisher = $this->createMock(EnrichedPublisher::class);
        $publisher->expects($this->never())
            ->method('publish');

        $pipeline = new NewsProcessingPipeline(
            [
                new DeduplicateStep($newsStore),
                new FinalizeStep,
            ],
            $publisher,
            $newsStore,
        );

        $pipeline->handle($this->rawNews());

        Event::assertNotDispatched(NewsEnriched::class);
    }

    public function test_listener_delegates_raw_news_to_pipeline_and_uses_intelligence_queue(): void
    {
        Event::fake();

        $newsStore = $this->createMock(NewsStore::class);
        $newsStore->expects($this->once())
            ->method('storeEnriched')
            ->with($this->callback(static function (EnrichedNewsData $enriched): bool {
                return $enriched->rawId === 77
                    && $enriched->status === NewsStatus::PUBLISHED;
            }));

        $publisher = $this->createMock(EnrichedPublisher::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with($this->callback(static function (EnrichedNewsData $enriched): bool {
                return $enriched->rawId === 77;
            }));

        $step = new class implements PipelineStep
        {
            public function process(RawNewsData|EnrichedNewsData $input): EnrichedNewsData
            {
                if (! $input instanceof RawNewsData) {
                    return $input;
                }

                return new EnrichedNewsData(
                    rawId: 77,
                    titleGenerated: 'Generated title',
                    contentTranslated: 'Translated content',
                    sentiment: 2,
                    category: 'IT',
                    tags: ['it'],
                    importance: false,
                    status: NewsStatus::PUBLISHED,
                    moderationReason: null,
                    fingerprint: $input->fingerprint,
                    analysisMetadata: [
                        'provider' => 'chatgpt_codex',
                    ],
                );
            }
        };

        $pipeline = new NewsProcessingPipeline([$step], $publisher, $newsStore);
        $listener = new ProcessRawNewsListener($pipeline);

        $this->assertSame('intelligence_tasks', $listener->viaQueue());

        $listener->handle(new RawNewsCreated($this->rawNews()));

        Event::assertDispatched(NewsEnriched::class, static fn (NewsEnriched $event): bool => $event->rawId === 77);
    }

    private function rawNews(): RawNewsData
    {
        return new RawNewsData(
            sourceId: 1,
            externalId: 'ext-1',
            title: 'Original title',
            link: 'https://example.com/news/1',
            content: 'Original content about Laravel growth',
            publishedAt: CarbonImmutable::parse('2026-03-17T10:00:00+00:00'),
            language: 'en',
            metadata: [],
            imageUrl: null,
            media: [],
            fingerprint: 'fp-1',
            rawId: null,
        );
    }
}
