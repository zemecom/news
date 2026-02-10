<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;
use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

final class SentimentStep implements PipelineStep
{
    public function __construct(private SentimentAnalyzer $sentiment)
    {
    }

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        $score = $this->sentiment->score($input->content);

        return new RawNewsData(
            sourceId: $input->sourceId,
            externalId: $input->externalId,
            title: $input->title,
            link: $input->link,
            content: $input->content,
            publishedAt: $input->publishedAt,
            language: $input->language,
            metadata: array_merge($input->metadata, ['sentiment' => $score]),
            fingerprint: $input->fingerprint,
            rawId: $input->rawId,
        );
    }
}
