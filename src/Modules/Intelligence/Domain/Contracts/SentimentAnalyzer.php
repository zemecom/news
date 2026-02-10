<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

interface SentimentAnalyzer
{
    public function score(string $content): int;
}
