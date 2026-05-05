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

    /**
     * @param  list<string>  $fingerprints
     * @return list<string>
     */
    public function existingFingerprints(array $fingerprints): array
    {
        $fingerprints = array_values(array_unique(array_filter(
            $fingerprints,
            static fn (string $fingerprint): bool => $fingerprint !== ''
        )));

        if ($fingerprints === []) {
            return [];
        }

        $existing = DB::table('news_items')
            ->whereIn('raw_fingerprint', $fingerprints)
            ->pluck('raw_fingerprint')
            ->all();

        return array_values(array_filter(
            array_map(static fn (mixed $fingerprint): string => (string) $fingerprint, $existing),
            static fn (string $fingerprint): bool => $fingerprint !== ''
        ));
    }
}
