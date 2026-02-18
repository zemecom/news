<?php

declare(strict_types=1);

use App\Http\Controllers\HealthController;
use App\Http\Controllers\Web\FeedPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', FeedPageController::class);

Route::get('/health/live', [HealthController::class, 'live']);
Route::get('/health/ready', [HealthController::class, 'ready']);
