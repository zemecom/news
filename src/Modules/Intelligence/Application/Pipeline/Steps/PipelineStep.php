<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

interface PipelineStep
{
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData;
}
