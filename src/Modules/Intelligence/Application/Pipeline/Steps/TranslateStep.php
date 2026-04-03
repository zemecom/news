<?php

declare(strict_types=1);

namespace Modules\Intelligence\Application\Pipeline\Steps;

use Modules\Intelligence\Domain\Contracts\Translator;
use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class TranslateStep implements PipelineStep
{
    public function __construct(private Translator $translator) {}

    public function process(RawNewsData|EnrichedNewsData $input): RawNewsData|EnrichedNewsData
    {
        if (! $input instanceof RawNewsData) {
            return $input;
        }

        if (isset($input->metadata['analysis']['provider']) && $input->language === 'ru') {
            return $input;
        }

        if ($input->language === 'ru') {
            return $input;
        }

        $originalContent = $input->content;
        $originalLanguage = $input->language;
        $translated = $this->translator->translate($originalContent, 'ru', $originalLanguage);

        return $input->with([
            'content' => $translated,
            'language' => 'ru',
            'metadata' => array_merge($input->metadata, [
                'original_content' => $originalContent,
                'original_language' => $originalLanguage,
            ]),
        ]);
    }
}
