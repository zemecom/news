<?php

declare(strict_types=1);

namespace Modules\Intelligence;

use Illuminate\Support\ServiceProvider;
use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep;
use Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep;
use Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep;
use Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep;
use Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep;
use Modules\Intelligence\Application\Pipeline\Steps\LanguageDetectStep;
use Modules\Intelligence\Application\Pipeline\Steps\ModerationStep;
use Modules\Intelligence\Application\Pipeline\Steps\SentimentStep;
use Modules\Intelligence\Application\Pipeline\Steps\TranslateStep;
use Modules\Intelligence\Domain\Contracts\Classifier;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher as EnrichedPublisherContract;
use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;
use Modules\Intelligence\Domain\Contracts\TitleGenerator;
use Modules\Intelligence\Domain\Contracts\Translator;
use Modules\Intelligence\Infrastructure\LLM\HeuristicTranslator;
use Modules\Intelligence\Infrastructure\LLM\KeywordClassifier;
use Modules\Intelligence\Infrastructure\LLM\KeywordSentimentAnalyzer;
use Modules\Intelligence\Infrastructure\LLM\ObjectivelyTitleGenerator;
use Modules\Intelligence\Infrastructure\Messaging\EnrichedPublisher;

final class IntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NewsProcessingPipeline::class, function ($app) {
            return new NewsProcessingPipeline(
                steps: $app->make('news.pipeline.steps.ordered'),
                publisher: $app->make(EnrichedPublisherContract::class),
                news: $app->make(NewsRepository::class),
            );
        });
        $this->app->singleton(EnrichedPublisher::class, function ($app) {
            return new EnrichedPublisher(
                connection: $app->make(\PhpAmqpLib\Connection\AMQPStreamConnection::class),
                exchange: (string) config('messaging.exchange.news_flow.name', 'news_flow'),
                readyRoutingKey: (string) config('messaging.routing_keys.enriched_ready', 'enriched.ready'),
                importantRoutingKey: (string) config('messaging.routing_keys.enriched_ready_important', 'enriched.ready.important'),
                rejectedRoutingKey: (string) config('messaging.routing_keys.enriched_rejected', 'enriched.rejected'),
            );
        });
        $this->app->bind(EnrichedPublisherContract::class, EnrichedPublisher::class);
        $this->app->bind(Translator::class, HeuristicTranslator::class);
        $this->app->bind(Classifier::class, KeywordClassifier::class);
        $this->app->bind(SentimentAnalyzer::class, KeywordSentimentAnalyzer::class);
        $this->app->bind(TitleGenerator::class, ObjectivelyTitleGenerator::class);

        $this->app->tag([
            DeduplicateStep::class,
            LanguageDetectStep::class,
            TranslateStep::class,
            ClassifyStep::class,
            SentimentStep::class,
            AntiClickbaitStep::class,
            ImportanceStep::class,
            ModerationStep::class,
            FinalizeStep::class,
        ], 'news.pipeline.steps');

        $this->app->bind('news.pipeline.steps.ordered', function ($app) {
            return [
                $app->make(DeduplicateStep::class),
                $app->make(LanguageDetectStep::class),
                $app->make(TranslateStep::class),
                $app->make(ClassifyStep::class),
                $app->make(SentimentStep::class),
                $app->make(AntiClickbaitStep::class),
                $app->make(ImportanceStep::class),
                $app->make(ModerationStep::class),
                $app->make(FinalizeStep::class),
            ];
        });
    }
}
