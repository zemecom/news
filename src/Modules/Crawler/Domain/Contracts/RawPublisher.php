<?php

declare(strict_types=1);

namespace Modules\Crawler\Domain\Contracts;

use Modules\Shared\Domain\DTO\RawNewsData;

interface RawPublisher
{
    public function publish(RawNewsData $raw): void;
}
