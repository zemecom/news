<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline;

use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final class NewsProcessingPipeline
{
    /** @var PipelineStep[] */
    private array $steps;

    /** @param PipelineStep[] $steps */
    public function __construct(
        array $steps,
        private EnrichedPublisher $publisher,
        private NewsRepository $news,
    ) {
        $this->steps = $steps;
    }

    public function handle(RawNewsData $raw): void
    {
        $context = $raw;
        foreach ($this->steps as $step) {
            $context = $step->process($context);
        }

        if ($context instanceof EnrichedNewsData) {
            $this->news->storeEnriched($context);
            $this->publisher->publish($context);
        }
    }
}
