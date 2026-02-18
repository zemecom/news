<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

use Override;

final class TechCrunchRssParser extends DefaultRssParser
{
    #[Override]
    public function supports(string $url): bool
    {
        return str_contains($url, 'techcrunch.com');
    }
}
