<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AdminSourceStoreRequest;
use App\Http\Requests\Api\V1\AdminSourceUpdateRequest;
use App\Support\Api\ApiResponse;
use App\Support\Api\V1\SourcePresenter;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Modules\Catalog\Infrastructure\Persistence\Models\Source;
use Modules\Delivery\Application\Actions\ListSourcesAction;

final class AdminSourceController extends Controller
{
    public function __construct(private readonly ListSourcesAction $listSources) {}

    public function index(): JsonResponse
    {
        return ApiResponse::data(array_map(
            static fn (array $source): array => SourcePresenter::admin($source),
            ($this->listSources)(),
        ));
    }

    public function show(Source $source): JsonResponse
    {
        return ApiResponse::data(SourcePresenter::admin($source));
    }

    public function store(AdminSourceStoreRequest $request): JsonResponse
    {
        /** @var Source $source */
        $source = Source::query()->create($this->payload($request->validated()));

        return ApiResponse::data(SourcePresenter::admin($source), status: 201);
    }

    public function update(AdminSourceUpdateRequest $request, Source $source): JsonResponse
    {
        $source->fill($this->payload($request->validated()));
        $source->save();

        return ApiResponse::data(SourcePresenter::admin($source->refresh()));
    }

    public function destroy(Source $source): JsonResponse
    {
        $source->delete();

        return ApiResponse::data([
            'deleted' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function payload(array $validated): array
    {
        foreach (['last_success_at', 'last_error_at'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null) {
                $validated[$field] = CarbonImmutable::parse((string) $validated[$field]);
            }
        }

        return $validated;
    }
}
