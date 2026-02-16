<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

class TheVergeRssParser extends DefaultRssParser
{
    public function supports(string $url): bool
    {
        return str_contains($url, 'theverge.com');
    }
}
