<?php

declare(strict_types=1);

namespace Modules\Intelligence;

use Illuminate\Support\ServiceProvider;
use Modules\Intelligence\Application\Pipeline\NewsProcessingPipeline;
use Modules\Intelligence\Application\Pipeline\Steps\DeduplicateStep;
use Modules\Intelligence\Application\Pipeline\Steps\LanguageDetectStep;
use Modules\Intelligence\Application\Pipeline\Steps\TranslateStep;
use Modules\Intelligence\Application\Pipeline\Steps\ClassifyStep;
use Modules\Intelligence\Application\Pipeline\Steps\SentimentStep;
use Modules\Intelligence\Application\Pipeline\Steps\AntiClickbaitStep;
use Modules\Intelligence\Application\Pipeline\Steps\ImportanceStep;
use Modules\Intelligence\Application\Pipeline\Steps\ModerationStep;
use Modules\Intelligence\Application\Pipeline\Steps\FinalizeStep;
use Modules\Intelligence\Infrastructure\Messaging\EnrichedPublisher;
use Modules\Catalog\Domain\Contracts\NewsRepository;

final class IntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NewsProcessingPipeline::class, function ($app) {
            return new NewsProcessingPipeline(
                steps: $app->make('news.pipeline.steps.ordered'),
                publisher: $app->make(EnrichedPublisher::class),
                news: $app->make(NewsRepository::class),
            );
        });
        $this->app->singleton(EnrichedPublisher::class);

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
