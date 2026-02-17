<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HealthCheckService;

final class HealthController extends Controller
{
    public function __construct(private readonly HealthCheckService $health) {}

    /**
     * @return array{status:string}
     */
    public function live(): array
    {
        return ['status' => 'ok'];
    }

    public function ready(): \Illuminate\Http\JsonResponse
    {
        $checks = $this->health->check();
        $allOk = ! in_array('fail', $checks, true);

        return response()->json($checks, $allOk ? 200 : 503);
    }
}
