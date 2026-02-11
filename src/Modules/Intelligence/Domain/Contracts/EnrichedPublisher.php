<?php

declare(strict_types=1);

namespace Modules\Intelligence\Domain\Contracts;

use Modules\Shared\Domain\DTO\EnrichedNewsData;

interface EnrichedPublisher
{
    public function publish(EnrichedNewsData $enriched): void;
}
