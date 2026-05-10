<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminNewsBulkActionRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\NewsPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Catalog\Infrastructure\Persistence\Models\NewsItem;
use Modules\Intelligence\Application\Services\EnqueueNewsAnalysisAction;

final class AdminNewsController extends Controller
{
    public function __construct(private readonly EnqueueNewsAnalysisAction $enqueueNewsAnalysis) {}

    public function index(Request $request): JsonResponse
    {
        $query = NewsItem::query()
            ->with('source')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('source_id')) {
            $query->where('source_id', $request->integer('source_id'));
        }

        if ($request->filled('q')) {
            $search = '%'.$request->string('q')->toString().'%';
            $query->where(function ($nested) use ($search): void {
                $nested
                    ->where('title_original', 'like', $search)
                    ->orWhere('title_generated', 'like', $search);
            });
        }

        /** @var list<NewsItem> $items */
        $items = $query->limit(max(1, min($request->integer('per_page', 25), 100)))->get()->all();

        return ApiResponse::data(array_map(
            static fn (NewsItem $item): array => NewsPresenter::adminListItem($item),
            $items,
        ));
    }

    public function show(NewsItem $newsItem): JsonResponse
    {
        $newsItem->loadMissing('source');

        return ApiResponse::data(NewsPresenter::adminDetail($newsItem));
    }

    public function reanalyze(NewsItem $newsItem): JsonResponse
    {
        $enqueued = $this->enqueueNewsAnalysis->enqueue((int) $newsItem->getKey()) ? 1 : 0;

        return ApiResponse::data([
            'enqueued' => $enqueued,
        ]);
    }

    public function bulkReanalyze(AdminNewsBulkActionRequest $request): JsonResponse
    {
        return ApiResponse::data([
            'enqueued' => $this->enqueueNewsAnalysis->enqueueMany($this->ids($request)),
        ]);
    }

    public function bulkEnrichMissingAi(AdminNewsBulkActionRequest $request): JsonResponse
    {
        $ids = $this->ids($request);
        /** @var list<int> $missingAnalysisIds */
        $missingAnalysisIds = NewsItem::query()
            ->whereIn('id', $ids)
            ->whereNull('source_metadata->analysis->provider')
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        return ApiResponse::data([
            'enqueued' => $this->enqueueNewsAnalysis->enqueueMany($missingAnalysisIds),
        ]);
    }

    public function bulkRefreshAi(AdminNewsBulkActionRequest $request): JsonResponse
    {
        return ApiResponse::data([
            'enqueued' => $this->enqueueNewsAnalysis->enqueueMany($this->ids($request)),
        ]);
    }

    /**
     * @return list<int>
     */
    private function ids(AdminNewsBulkActionRequest $request): array
    {
        $ids = $request->input('ids', []);

        if (! is_array($ids)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids,
        ));
    }
}
