<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class SourceFetchFailed
{
    use Dispatchable;

    public function __construct(
        public int $sourceId,
        public string $errorMessage,
    ) {}
}
