<?php

declare(strict_types=1);

namespace Modules\Delivery\Domain\DTO;

use Carbon\CarbonImmutable;

readonly class NewsFeedFilters
{
    public function __construct(
        public ?string $category = null,
        public ?int $sentimentMin = null,
        public ?int $sentimentMax = null,
        public ?bool $important = null,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public ?string $query = null,
        public ?int $sourceId = null,
    ) {
    }
}
