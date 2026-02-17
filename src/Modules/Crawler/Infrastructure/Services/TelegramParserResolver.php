<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Services;

use Modules\Crawler\Domain\Contracts\TelegramParser;
use Modules\Crawler\Infrastructure\Parsers\Telegram\DefaultTelegramParser;

class TelegramParserResolver
{
    /**
     * @param  iterable<TelegramParser>  $parsers
     */
    public function __construct(
        private readonly iterable $parsers,
        private readonly DefaultTelegramParser $defaultParser
    ) {}

    public function resolve(string $channel): TelegramParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser instanceof DefaultTelegramParser) {
                continue; // Skip default parser in the loop
            }

            if ($parser->supports($channel)) {
                return $parser;
            }
        }

        return $this->defaultParser;
    }
}
