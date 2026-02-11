<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

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

        return $input->with([
            'metadata' => array_merge($input->metadata, ['importance' => $importance]),
        ]);
    }
}
