<?php

declare(strict_types=1);

namespace Modules\Crawler\Infrastructure\Services;

use Illuminate\Support\Facades\DB;
use Modules\Crawler\Domain\Contracts\Deduplicator;

final class DbDeduplicator implements Deduplicator
{
    public function exists(string $fingerprint): bool
    {
        return DB::table('news_items')
            ->where('raw_fingerprint', $fingerprint)
            ->exists();
    }
}
