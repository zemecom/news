<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

final class ImportanceStep implements PipelineStep
{
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $category = $input->metadata['category'] ?? null;
        $sentiment = (int) ($input->metadata['sentiment'] ?? 0);
        $importantCategories = ['Экономика', 'Политика', 'IT'];
        $importance = in_array($category, $importantCategories, true) && abs($sentiment) > 3;

        return new RawNewsData(
            sourceId: $input->sourceId,
            externalId: $input->externalId,
            title: $input->title,
            link: $input->link,
            content: $input->content,
            publishedAt: $input->publishedAt,
            language: $input->language,
            metadata: array_merge($input->metadata, ['importance' => $importance]),
            fingerprint: $input->fingerprint,
            rawId: $input->rawId,
        );
    }
}
