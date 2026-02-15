<?php

declare(strict_types=1);

namespace Modules\Delivery\Application\Actions;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Delivery\Domain\Contracts\NewsFeedReader;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;

final class ListNewsAction
{
    public function __construct(private NewsFeedReader $reader) {}

    /**
     * @return CursorPaginator<int, array<string, mixed>>
     */
    public function __invoke(NewsFeedFilters $filters, int $perPage = 20, ?string $cursor = null): CursorPaginator
    {
        return $this->reader->paginatePublished($filters, $perPage, $cursor);
    }
}
