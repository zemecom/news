<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\Classifier;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

final class ClassifyStep implements PipelineStep
{
    public function __construct(private Classifier $classifier)
    {
    }

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $classification = $this->classifier->classify($input->content);

        return new RawNewsData(
            sourceId: $input->sourceId,
            externalId: $input->externalId,
            title: $input->title,
            link: $input->link,
            content: $input->content,
            publishedAt: $input->publishedAt,
            language: $input->language,
            metadata: array_merge($input->metadata, $classification),
            fingerprint: $input->fingerprint,
            rawId: $input->rawId,
        );
    }
}
