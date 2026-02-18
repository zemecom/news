<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Services;

use Modules\Crawler\Domain\Contracts\RssParser;
use Modules\Crawler\Infrastructure\Parsers\DefaultRssParser;

final readonly class RssParserResolver
{
    /**
     * @param  iterable<RssParser>  $parsers
     */
    public function __construct(
        private iterable $parsers,
        private DefaultRssParser $defaultParser
    ) {}

    public function resolve(string $url): RssParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser instanceof DefaultRssParser) {
                continue; // Skip default parser in the loop, we use it as fallback
            }

            if ($parser->supports($url)) {
                return $parser;
            }
        }

        return $this->defaultParser;
    }
}
