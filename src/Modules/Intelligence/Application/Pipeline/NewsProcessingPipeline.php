<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline;

use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Events\NewsEnriched;

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
    public function __construct(private array $steps, private EnrichedPublisher $publisher, private NewsStore $news) {}

    public function handle(RawNewsData $raw): void
    {
        $context = $raw;

        try {
            foreach ($this->steps as $step) {
                $context = $step->process($context);
            }
        } catch (SkipMessageException) {
            return;
        }

        if ($context instanceof EnrichedNewsData) {
            $this->news->storeEnriched($context);
            event(new NewsEnriched($context->rawId));
            $this->publisher->publish($context);
        }
    }
}
