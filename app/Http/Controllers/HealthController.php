<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use Illuminate\Http\JsonResponse;

final class HealthController extends Controller
{
    /**
     * @return array{status:string}
     */
    public function live(): array
    {
        return ['status' => 'ok'];
    }

    public function ready(HealthCheckService $health): JsonResponse
    {
        $checks = $health->check();
        $allOk = ! in_array('fail', $checks, true);

        return response()->json($checks, $allOk ? 200 : 503);
    }
}
