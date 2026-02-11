<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\Classifier;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final class ClassifyStep implements PipelineStep
{
    public function __construct(private Classifier $classifier) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $classification = $this->classifier->classify($input->content);

        return $input->with([
            'metadata' => array_merge($input->metadata, $classification),
        ]);
    }
}
