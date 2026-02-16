<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection;

interface TelegramParser
{
    /**
     * @param  array{channel: string}  $context
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(string $htmlBody, array $context): Collection;

    public function supports(string $channel): bool;
}
