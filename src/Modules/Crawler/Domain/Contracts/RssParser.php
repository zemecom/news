<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection;

interface RssParser
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(string $xmlBody): Collection;

    public function supports(string $url): bool;
}
