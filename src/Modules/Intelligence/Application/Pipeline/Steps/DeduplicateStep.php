<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Catalog\Domain\Contracts\NewsRepository;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;

final class DeduplicateStep implements PipelineStep
{
    public function __construct(private NewsRepository $news) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if ($this->news->existsByFingerprint($input->fingerprint)) {
            $rawId = $this->news->findIdByFingerprint($input->fingerprint);

            return new EnrichedNewsData(
                rawId: $rawId,
                titleGenerated: $input->title,
                contentTranslated: $input->content,
                sentiment: 0,
                category: 'duplicate',
                tags: [],
                importance: false,
                status: NewsStatus::REJECTED,
                moderationReason: 'duplicate',
                fingerprint: $input->fingerprint,
            );
        }

        $rawId = $this->news->storeRaw($input);

        return $input->with(['rawId' => $rawId]);
    }
}
