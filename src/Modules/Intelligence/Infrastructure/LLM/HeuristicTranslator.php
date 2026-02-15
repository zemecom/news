<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Translator;

final class HeuristicTranslator implements Translator
{
    public function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        if ($text === '' || $targetLanguage === $sourceLanguage) {
            return $text;
        }

        if ($targetLanguage !== 'ru') {
            return $text;
        }

        return $text;
    }
}
