<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Contracts;

interface SourcePublicReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listActive(): array;
}
