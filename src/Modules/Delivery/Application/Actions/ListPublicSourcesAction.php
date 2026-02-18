<?php

declare(strict_types=1);

namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\SourcePublicReader;

final readonly class ListPublicSourcesAction
{
    public function __construct(private SourcePublicReader $reader) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return $this->reader->listActive();
    }
}
