<?php

declare(strict_types=1);

namespace Modules\Shared\Domain\Events;

use Modules\Shared\Domain\DTO\RawNewsData;

final readonly class RawNewsCreated
{
    public function __construct(
        public RawNewsData $raw,
    ) {}
}
