<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Illuminate\Support\Collection;

interface TelegramClient
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function fetch(string $channel): Collection;
}
