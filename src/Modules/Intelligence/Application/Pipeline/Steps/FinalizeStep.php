<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;

final class FinalizeStep implements PipelineStep
{
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if ($input instanceof EnrichedNewsData) {
            return $input;
        }

        $metadata = $input->metadata;
        return new EnrichedNewsData(
            rawId: $input->rawId ?? $input->externalId ?? $input->fingerprint,
            titleGenerated: $input->title,
            contentTranslated: $input->content,
            sentiment: (int) ($metadata['sentiment'] ?? 0),
            category: (string) ($metadata['category'] ?? ''),
            tags: $metadata['tags'] ?? [],
            importance: (bool) ($metadata['importance'] ?? false),
            status: NewsStatus::PUBLISHED,
            moderationReason: null,
            fingerprint: $input->fingerprint,
        );
    }
}
