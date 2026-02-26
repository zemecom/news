<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class SourceFetchFailed
{
    use Dispatchable;

    public function __construct(
        public int $sourceId,
        public string $errorMessage,
    ) {}
}
