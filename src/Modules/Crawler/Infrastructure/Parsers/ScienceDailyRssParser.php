<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

class ScienceDailyRssParser extends DefaultRssParser
{
    #[\Override]
    public function supports(string $url): bool
    {
        return str_contains($url, 'sciencedaily.com');
    }
}
