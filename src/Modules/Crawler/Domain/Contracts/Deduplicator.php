<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

interface Deduplicator
{
    public function exists(string $fingerprint): bool;

    /**
     * @param  list<string>  $fingerprints
     * @return list<string>
     */
    public function existingFingerprints(array $fingerprints): array;
}
