<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Shared\Domain\DTO\RawNewsData;
use Modules\Shared\Domain\DTO\EnrichedNewsData;

final class TranslateStep implements PipelineStep
{
    public function __construct(private \Modules\Intelligence\Domain\Contracts\Translator $translator)
    {
    }

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if ($input->language === 'ru') {
            return $input;
        }

        $translated = $this->translator->translate($input->content, 'ru', $input->language);

        return new RawNewsData(
            sourceId: $input->sourceId,
            externalId: $input->externalId,
            title: $input->title,
            link: $input->link,
            content: $translated,
            publishedAt: $input->publishedAt,
            language: 'ru',
            metadata: $input->metadata,
            fingerprint: $input->fingerprint,
            rawId: $input->rawId,
        );
    }
}
