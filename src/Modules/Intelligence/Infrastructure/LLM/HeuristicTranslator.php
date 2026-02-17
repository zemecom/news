<?php

declare(strict_types=1);

namespace Modules\Intelligence\Infrastructure\LLM;

use Modules\Intelligence\Domain\Contracts\Translator;

final class HeuristicTranslator implements Translator
{
    public function translate(string $text, string $targetLanguage, string $sourceLanguage): string
    {
        return $text;
    }
}
