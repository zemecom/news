<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Carbon\Carbon;
use Illuminate\Support\Collection;

interface TelegramClient
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $channel, ?Carbon $dateFrom = null, ?Carbon $dateTo = null, ?int $limit = null): Collection;
}
