<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\SentimentAnalyzer;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class SentimentStep implements PipelineStep
{
    public function __construct(private SentimentAnalyzer $sentiment) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if (array_key_exists('sentiment', $input->metadata)) {
            return $input;
        }

        $score = $this->sentiment->score($input->content);

        return $input->with([
            'metadata' => array_merge($input->metadata, ['sentiment' => $score]),
        ]);
    }
}
