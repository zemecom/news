<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

interface PipelineStep
{
    /**
     * @param RawNewsData|EnrichedNewsData $input
     * @return RawNewsData|EnrichedNewsData
     */
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData;
}
