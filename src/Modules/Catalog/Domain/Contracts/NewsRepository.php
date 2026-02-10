<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

interface NewsRepository
{
    public function existsByFingerprint(string $fingerprint): bool;

    public function findIdByFingerprint(string $fingerprint): string;

    public function storeRaw(RawNewsData $raw): string;

    public function storeEnriched(EnrichedNewsData $enriched): void;
}
