<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\Enum\NewsStatus;

final class ModerationStep implements PipelineStep
{
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $category = $input->metadata['category'] ?? '';
        if (strtolower($category) === 'бытовой криминал') {
            return new EnrichedNewsData(
                rawId: $input->rawId ?? $input->externalId ?? $input->fingerprint,
                titleGenerated: $input->title,
                contentTranslated: $input->content,
                sentiment: (int) ($input->metadata['sentiment'] ?? 0),
                category: $category,
                tags: $input->metadata['tags'] ?? [],
                importance: false,
                status: NewsStatus::REJECTED,
                moderationReason: 'household_crime',
                fingerprint: $input->fingerprint,
            );
        }

        return $input;
    }
}
