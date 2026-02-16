<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection;

interface ApiParser
{
    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array<string, mixed>>
     */
    public function parse(array $data): Collection;

    public function supports(string $source): bool;
}
