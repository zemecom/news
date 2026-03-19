<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Application\Pipeline\SkipMessageException;
use Modules\Shared\Domain\Contracts\NewsStore;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class DeduplicateStep implements PipelineStep
{
    public function __construct(private NewsStore $news) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if ($input->rawId !== null) {
            return $input;
        }

        if ($this->news->existsByFingerprint($input->fingerprint)) {
            throw new SkipMessageException('duplicate');
        }

        $rawId = $this->news->storeRaw($input);

        return $input->with(['rawId' => $rawId]);
    }
}
