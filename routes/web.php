<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Web\FeedPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', FeedPageController::class);

Route::prefix('api/v1/auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
