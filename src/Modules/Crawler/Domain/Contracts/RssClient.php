<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface RssClient
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $url, ?Carbon $dateFrom = null, ?Carbon $dateTo = null, ?int $limit = null): Collection;
}
