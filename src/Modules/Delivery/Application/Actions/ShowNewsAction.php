<?php

declare(strict_types=1);

namespace Modules\Delivery\Application\Actions;

use Modules\Delivery\Domain\Contracts\NewsFeedReader;

final class ShowNewsAction
{
    public function __construct(private NewsFeedReader $reader) {}

    /**
     * @return array<string, mixed>|null
     */
    public function __invoke(string $id): ?array
    {
        return $this->reader->findPublishedById($id);
    }
}
