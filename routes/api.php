<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Admin\SourceController;
use App\Http\Controllers\Api\NewsController;
use App\Http\Controllers\Api\V1\AdminAiProviderAccountController;
use App\Http\Controllers\Api\V1\AdminNewsController;
use App\Http\Controllers\Api\V1\AdminSettingsController;
use App\Http\Controllers\Api\V1\AdminSourceController as AdminSourceV1Controller;
use App\Http\Controllers\Api\V1\PublicNewsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/news', [PublicNewsController::class, 'index']);
    Route::get('/news/filters', [PublicNewsController::class, 'filters']);
    Route::get('/news/{id}', [PublicNewsController::class, 'show'])->whereNumber('id');

    Route::middleware(['auth:sanctum', 'role.admin'])
        ->prefix('admin')
        ->group(function (): void {
            Route::get('/news', [AdminNewsController::class, 'index']);
            Route::get('/news/{newsItem}', [AdminNewsController::class, 'show'])->whereNumber('newsItem');
            Route::post('/news/{newsItem}/reanalyze', [AdminNewsController::class, 'reanalyze'])->whereNumber('newsItem');
            Route::post('/news/bulk/reanalyze', [AdminNewsController::class, 'bulkReanalyze']);
            Route::post('/news/bulk/enrich-missing-ai', [AdminNewsController::class, 'bulkEnrichMissingAi']);
            Route::post('/news/bulk/refresh-ai', [AdminNewsController::class, 'bulkRefreshAi']);

            Route::get('/sources', [AdminSourceV1Controller::class, 'index']);
            Route::post('/sources', [AdminSourceV1Controller::class, 'store']);
            Route::get('/sources/{source}', [AdminSourceV1Controller::class, 'show'])->whereNumber('source');
            Route::patch('/sources/{source}', [AdminSourceV1Controller::class, 'update'])->whereNumber('source');
            Route::delete('/sources/{source}', [AdminSourceV1Controller::class, 'destroy'])->whereNumber('source');

            Route::get('/settings', [AdminSettingsController::class, 'show']);
            Route::put('/settings', [AdminSettingsController::class, 'update']);

            Route::get('/ai-provider-accounts', [AdminAiProviderAccountController::class, 'index']);
            Route::get('/ai-provider-accounts/{aiProviderAccount}', [AdminAiProviderAccountController::class, 'show'])->whereNumber('aiProviderAccount');
            Route::patch('/ai-provider-accounts/{aiProviderAccount}', [AdminAiProviderAccountController::class, 'update'])->whereNumber('aiProviderAccount');
            Route::post('/ai-provider-accounts/{aiProviderAccount}/sync', [AdminAiProviderAccountController::class, 'sync'])->whereNumber('aiProviderAccount');
            Route::post('/ai-provider-accounts/{aiProviderAccount}/login', [AdminAiProviderAccountController::class, 'login'])->whereNumber('aiProviderAccount');
            Route::post('/ai-provider-accounts/{aiProviderAccount}/cancel-login', [AdminAiProviderAccountController::class, 'cancelLogin'])->whereNumber('aiProviderAccount');
            Route::post('/ai-provider-accounts/{aiProviderAccount}/logout', [AdminAiProviderAccountController::class, 'logout'])->whereNumber('aiProviderAccount');
        });
});

Route::get('/news', [NewsController::class, 'index']);
Route::get('/news/{id}', [NewsController::class, 'show'])->whereNumber('id');
Route::get('/sources', [NewsController::class, 'sources']);

Route::middleware(['auth', 'role.admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::get('/sources', [SourceController::class, 'index']);
    });
