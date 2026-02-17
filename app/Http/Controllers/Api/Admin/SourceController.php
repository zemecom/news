<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Delivery\Application\Actions\ListSourcesAction;

final class SourceController extends Controller
{
    public function __construct(private readonly ListSourcesAction $listSources) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ($this->listSources)(),
        ]);
    }
}
