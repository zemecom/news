<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

interface Translator
{
    public function translate(string $text, string $targetLanguage, string $sourceLanguage): string;
}
