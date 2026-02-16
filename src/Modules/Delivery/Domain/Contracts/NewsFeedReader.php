<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\Contracts;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;

interface NewsFeedReader
{
    /**
     * @return CursorPaginator<int, array<string, mixed>>
     */
    public function paginatePublished(NewsFeedFilters $filters, int $perPage, ?string $cursor): CursorPaginator;

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedById(string $id): ?array;

    public function count(NewsFeedFilters $filters): int;
}
