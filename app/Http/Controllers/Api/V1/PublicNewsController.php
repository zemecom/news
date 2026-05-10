<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewsIndexRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\NewsPresenter;
use App\Support\Api\V1\SourcePresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Modules\Delivery\Application\Actions\ListNewsAction;
use Modules\Delivery\Application\Actions\ListPublicSourcesAction;
use Modules\Delivery\Application\Actions\ShowNewsAction;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;

final class PublicNewsController extends Controller
{
    public function __construct(
        private readonly ListNewsAction $listNews,
        private readonly ShowNewsAction $showNews,
        private readonly ListPublicSourcesAction $listPublicSources,
    ) {}

    public function index(NewsIndexRequest $request): JsonResponse
    {
        $filters = $this->filtersFromRequest($request);
        $paginator = ($this->listNews)(
            filters: $filters,
            perPage: max(1, min($request->integer('per_page', 20), 100)),
            cursor: $request->string('cursor')->toString() ?: null,
        );

        return ApiResponse::data(
            data: array_map(
                static fn (array $item): array => NewsPresenter::publicListItem($item),
                $paginator->items(),
            ),
            meta: [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'total' => $this->listNews->count($filters),
            ],
        );
    }

    public function show(string $id): JsonResponse
    {
        $item = ($this->showNews)($id);

        if ($item === null) {
            return ApiResponse::error('not_found', 'News item not found.', 404);
        }

        return ApiResponse::data(NewsPresenter::publicDetail($item));
    }

    public function filters(): JsonResponse
    {
        return ApiResponse::data([
            'categories' => array_values(config('intelligence.categories', [])),
            'sources' => array_map(
                static fn (array $source): array => SourcePresenter::public($source),
                ($this->listPublicSources)(),
            ),
            'sentiment_range' => [
                'min' => -10,
                'max' => 10,
            ],
        ]);
    }

    private function filtersFromRequest(NewsIndexRequest $request): NewsFeedFilters
    {
        return new NewsFeedFilters(
            category: $request->string('category')->toString() ?: null,
            sentimentMin: $request->filled('sentiment_min') ? $request->integer('sentiment_min') : null,
            sentimentMax: $request->filled('sentiment_max') ? $request->integer('sentiment_max') : null,
            important: $request->has('important') ? $request->boolean('important') : null,
            dateFrom: $request->filled('date_from')
                ? CarbonImmutable::parse($request->string('date_from')->toString())->startOfDay()
                : null,
            dateTo: $request->filled('date_to')
                ? CarbonImmutable::parse($request->string('date_to')->toString())->endOfDay()
                : null,
            query: $request->string('q')->toString() ?: null,
            sourceId: $request->filled('source_id') ? $request->integer('source_id') : null,
        );
    }
}
