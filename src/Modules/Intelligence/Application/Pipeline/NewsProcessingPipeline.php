<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline;

use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Intelligence\Application\Pipeline\Steps\PipelineStep;
use Modules\Intelligence\Domain\Contracts\EnrichedPublisher;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class NewsProcessingPipeline
{
    /** @param PipelineStep[] $steps */
    public function __construct(private array $steps, private EnrichedPublisher $publisher, private NewsRepository $news) {}

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
            dispatch(new \Modules\Catalog\Application\Jobs\PreloadNewsMediaJob($context->rawId));
            $this->publisher->publish($context);
        }
    }
}
