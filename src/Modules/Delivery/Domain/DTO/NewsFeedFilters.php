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
    ) {}

    public static function fromRequest(\Illuminate\Http\Request $request): self
    {
        return new self(
            category: $request->string('category')->toString() ?: null,
            sentimentMin: $request->filled('sentiment_min') ? $request->integer('sentiment_min') : null,
            sentimentMax: $request->filled('sentiment_max') ? $request->integer('sentiment_max') : null,
            important: $request->has('important') ? $request->boolean('important') : null,
            dateFrom: $request->filled('date_from') ? CarbonImmutable::parse($request->string('date_from')->toString()) : null,
            dateTo: $request->filled('date_to') ? CarbonImmutable::parse($request->string('date_to')->toString()) : null,
            query: $request->string('q')->toString() ?: null,
            sourceId: $request->filled('source_id') ? $request->integer('source_id') : null,
        );
    }
}
