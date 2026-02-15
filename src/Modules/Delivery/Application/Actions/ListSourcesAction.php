<?php

declare(strict_types=1);

namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\SourceAdminReader;

final class ListSourcesAction
{
    public function __construct(private SourceAdminReader $reader) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function __invoke(): array
    {
        return $this->reader->list();
    }
}
