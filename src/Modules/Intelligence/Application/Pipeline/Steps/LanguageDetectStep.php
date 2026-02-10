<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

final class LanguageDetectStep implements PipelineStep
{
    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $lang = $input->language !== '' ? $input->language : 'en';
        return new RawNewsData(
            sourceId: $input->sourceId,
            externalId: $input->externalId,
            title: $input->title,
            link: $input->link,
            content: $input->content,
            publishedAt: $input->publishedAt,
            language: $lang,
            metadata: $input->metadata,
            fingerprint: $input->fingerprint,
            rawId: $input->rawId,
        );
    }
}
