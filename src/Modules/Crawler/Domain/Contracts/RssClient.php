<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection;

interface RssClient
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $url, ?\Carbon\Carbon $dateFrom = null, ?\Carbon\Carbon $dateTo = null, ?int $limit = null): Collection;
}
