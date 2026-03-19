<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline;

use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep;
use Modules\Intelligence\Application\Services\NewsAnalysisRuntimeRecorder;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Events\NewsEnriched;
use Throwable;

/**
 * Конвейер (Pipeline) обработки сырых новостей в модуле Intelligence (Слой: Application).
 *
 * Реализует паттерн Chain of Responsibility / Pipeline.
 * 1. Принимает DTO `RawNewsData` на вход.
 * 2. Прогоняет его через набор шагов (`PipelineStep`): перевод, суммаризация, сентимент-анализ и т.д.
 * 3. Если шаг выбрасывает `SkipMessageException` — обработка прерывается.
 * 4. Если результат стал `EnrichedNewsData`, конвейер сохраняет результат в БД (Catalog),
 *    вызывает доменное событие `NewsEnriched` и пушит его подписчикам для дальнейшей доставки.
 */
final readonly class NewsProcessingPipeline
{
    /** @param PipelineStep[] $steps */
    public function __construct(
        private array $steps,
        private EnrichedPublisher $publisher,
        private NewsStore $news,
        private NewsAnalysisRuntimeRecorder $runtimeRecorder,
    ) {}

    public function handle(RawNewsData $raw): void
    {
        $context = $raw;
        $runtimeNewsItemId = $raw->rawId;
        $currentStepKey = null;
        $currentStepLabel = null;

        if ($runtimeNewsItemId !== null) {
            $this->runtimeRecorder->markRunning($runtimeNewsItemId);
        }

        try {
            foreach ($this->steps as $step) {
                $currentStepKey = $this->stepKey($step);
                $currentStepLabel = $this->stepLabel($step);

                if ($runtimeNewsItemId !== null) {
                    $this->runtimeRecorder->recordStepStarted($runtimeNewsItemId, $currentStepKey, $currentStepLabel);
                }

                $context = $step->process($context);

                $runtimeNewsItemId = $this->extractNewsItemId($context) ?? $runtimeNewsItemId;

                if ($runtimeNewsItemId !== null) {
                    $this->runtimeRecorder->markRunning($runtimeNewsItemId);
                    $this->runtimeRecorder->recordStepCompleted($runtimeNewsItemId, $currentStepKey, $currentStepLabel);
                }
            }
        } catch (SkipMessageException $e) {
            if ($runtimeNewsItemId !== null) {
                if (is_string($currentStepKey) && is_string($currentStepLabel)) {
                    $this->runtimeRecorder->recordStepFailed(
                        $runtimeNewsItemId,
                        $currentStepKey,
                        $currentStepLabel,
                        'Pipeline skipped: '.$e->getMessage(),
                    );
                }

                $this->runtimeRecorder->markFailed($runtimeNewsItemId, 'Pipeline skipped: '.$e->getMessage());
            }

            return;
        } catch (Throwable $e) {
            if ($runtimeNewsItemId !== null) {
                if (is_string($currentStepKey) && is_string($currentStepLabel)) {
                    $this->runtimeRecorder->recordStepFailed(
                        $runtimeNewsItemId,
                        $currentStepKey,
                        $currentStepLabel,
                        $e->getMessage(),
                    );
                }

                $this->runtimeRecorder->markFailed($runtimeNewsItemId, $e->getMessage());
            }

            throw $e;
        }

        if ($context instanceof EnrichedNewsData) {
            $this->news->storeEnriched($context);
            event(new NewsEnriched($context->rawId));
            $this->publisher->publish($context);
            $this->runtimeRecorder->markCompleted($context->rawId);
        }
    }

    private function extractNewsItemId(RawNewsData|EnrichedNewsData $context): ?int
    {
        if ($context instanceof EnrichedNewsData) {
            return $context->rawId;
        }

        return $context->rawId;
    }

    private function stepKey(PipelineStep $step): string
    {
        return (string) str(class_basename($step))
            ->beforeLast('Step')
            ->snake();
    }

    private function stepLabel(PipelineStep $step): string
    {
        return (string) str(class_basename($step))
            ->beforeLast('Step')
            ->headline();
    }
}
