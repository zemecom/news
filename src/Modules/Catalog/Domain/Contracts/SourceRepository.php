<?php

declare(strict_types=1);

namespace Modules\Catalog\Domain\Contracts;

interface SourceRepository
{
    public function updateSuccess(int $sourceId): void;

    public function updateFailure(int $sourceId): void;
}
