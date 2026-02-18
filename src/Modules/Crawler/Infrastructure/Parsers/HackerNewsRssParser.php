<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Parsers;

use Override;

final class HackerNewsRssParser extends DefaultRssParser
{
    #[Override]
    public function supports(string $url): bool
    {
        return str_contains($url, 'hnrss.org');
    }
}
