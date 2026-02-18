<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\SourceController;
use App\Http\Controllers\Api\NewsController;
use Illuminate\Support\Facades\Route;

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show'])->whereNumber('id');
Route::get('/sources', [NewsController::class, 'sources']);

Route::middleware(['auth', 'role.admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/sources', [SourceController::class, 'index']);
    });
