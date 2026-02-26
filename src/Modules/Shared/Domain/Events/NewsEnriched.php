<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Events;

use Illuminate\Foundation\Events\Dispatchable;

final readonly class NewsEnriched
{
    use Dispatchable;

    public function __construct(
        public int $rawId,
    ) {}
}
