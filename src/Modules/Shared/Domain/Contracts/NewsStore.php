<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData;
use Modules\Shared\Domain\DTO\RawNewsData;

interface NewsStore
{
    public function existsByFingerprint(string $fingerprint): bool;

    public function storeRaw(RawNewsData $raw): int;

    public function storeEnriched(EnrichedNewsData $enriched): void;
}
