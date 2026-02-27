<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\NewsIndexRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Modules\Delivery\Application\Actions\ListNewsAction;
use Modules\Delivery\Application\Actions\ListPublicSourcesAction;
use Modules\Delivery\Application\Actions\ShowNewsAction;
use Modules\Delivery\Domain\DTO\NewsFeedFilters;

/**
 * API контроллер для отдачи новостей клиентам (Web, Mobile).
 *
 * В рамках Модульного Монолита этот класс (слой Framework) выступает лишь "официантом".
 * Он не содержит бизнес-логики и SQL-запросов. Его единственная задача:
 * 1. Принять HTTP-запрос (NewsIndexRequest)
 * 2. Сконвертировать данные в строго типизированный DTO (NewsFeedFilters)
 * 3. Передать DTO в слой Application модуля Delivery (ListNewsAction)
 * 4. Вернуть полученный ответ в формате JSON.
 */
final class NewsController extends Controller
{
    public function __construct(
        private readonly ListNewsAction $listNews,
        private readonly ShowNewsAction $showNews,
        private readonly ListPublicSourcesAction $listPublicSources,
    ) {}

    public function index(NewsIndexRequest $request): JsonResponse
    {
        $filters = new NewsFeedFilters(
            category: $request->string('category')->toString() ?: null,
            sentimentMin: $request->filled('sentiment_min') ? $request->integer('sentiment_min') : null,
            sentimentMax: $request->filled('sentiment_max') ? $request->integer('sentiment_max') : null,
            important: $request->has('important') ? $request->boolean('important') : null,
            dateFrom: $request->filled('date_from') ? CarbonImmutable::parse($request->string('date_from')->toString()) : null,
            dateTo: $request->filled('date_to') ? CarbonImmutable::parse($request->string('date_to')->toString()) : null,
            query: $request->string('q')->toString() ?: null,
            sourceId: $request->filled('source_id') ? $request->integer('source_id') : null,
        );

        $paginator = ($this->listNews)(
            filters: $filters,
            perPage: max(1, min($request->integer('per_page', 20), 100)),
            cursor: $request->string('cursor')->toString() ?: null,
        );

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'total' => $this->listNews->count($filters),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $newsItem = ($this->showNews)($id);
        if ($newsItem === null) {
            return response()->json(['message' => 'News item not found.'], 404);
        }

        return response()->json([
            'data' => $newsItem,
        ]);
    }

    public function sources(): JsonResponse
    {
        return response()->json([
            'data' => ($this->listPublicSources)(),
        ]);
    }
}
