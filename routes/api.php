<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\IntegrationController;
use App\Http\Controllers\Api\KeywordController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    // Sites
    Route::apiResource('sites', SiteController::class)->names([
        'index' => 'api.sites.index',
        'store' => 'api.sites.store',
        'show' => 'api.sites.show',
        'update' => 'api.sites.update',
        'destroy' => 'api.sites.destroy',
    ]);

    // Keywords (nested under sites)
    Route::prefix('sites/{site}')->group(function () {
        Route::get('keywords', [KeywordController::class, 'index']);
        Route::post('keywords', [KeywordController::class, 'store']);
        Route::delete('keywords/{keyword}', [KeywordController::class, 'destroy']);
        Route::post('keywords/discover', [KeywordController::class, 'discover']);
        Route::post('keywords/cluster', [KeywordController::class, 'cluster']);
    });

    // Articles (nested under sites for listing)
    Route::get('sites/{site}/articles', [ArticleController::class, 'index']);

    // Articles (standalone for CRUD)
    Route::get('articles/{article}', [ArticleController::class, 'show']);
    Route::put('articles/{article}', [ArticleController::class, 'update']);
    Route::delete('articles/{article}', [ArticleController::class, 'destroy']);
    Route::post('articles/{article}/publish', [ArticleController::class, 'publish']);

    // Generate article from keyword
    Route::post('keywords/{keyword}/generate', [ArticleController::class, 'generate']);

    // Integrations
    Route::apiResource('integrations', IntegrationController::class)->names([
        'index' => 'api.integrations.index',
        'store' => 'api.integrations.store',
        'show' => 'api.integrations.show',
        'update' => 'api.integrations.update',
        'destroy' => 'api.integrations.destroy',
    ]);
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'test']);
    Route::get('integrations/{integration}/categories', [IntegrationController::class, 'categories']);

    // Analytics
    Route::get('sites/{site}/analytics', [AnalyticsController::class, 'dashboard'])->name('api.analytics.dashboard');
    Route::get('articles/{article}/analytics', [AnalyticsController::class, 'article'])->name('api.analytics.article');
    Route::post('sites/{site}/analytics/sync', [AnalyticsController::class, 'sync'])->name('api.analytics.sync');

    // Content Plan
    Route::get('sites/{site}/generation-status', [\App\Http\Controllers\Api\ContentPlanController::class, 'generationStatus']);
    Route::get('sites/{site}/content-plan', [\App\Http\Controllers\Api\ContentPlanController::class, 'contentPlan']);
});

// Google OAuth (doesn't require auth for callback)
Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->middleware('auth:sanctum');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);
Route::post('sites/{site}/google/disconnect', [GoogleAuthController::class, 'disconnect'])->middleware('auth:sanctum');
