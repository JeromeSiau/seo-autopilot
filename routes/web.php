<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\ArticleController;
use App\Http\Controllers\Web\IntegrationController;
use App\Http\Controllers\Web\KeywordController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SiteController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

// Google OAuth
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Onboarding Wizard
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::post('/onboarding/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
    Route::post('/onboarding/{site}/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
    Route::post('/onboarding/{site}/step3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
    Route::post('/onboarding/{site}/step4', [OnboardingController::class, 'storeStep4'])->name('onboarding.step4');
    Route::post('/onboarding/{site}/step5', [OnboardingController::class, 'storeStep5'])->name('onboarding.step5');
    Route::post('/onboarding/{site}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

    // Sites
    Route::resource('sites', SiteController::class);

    // Keywords
    Route::get('/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::get('/keywords/create', [KeywordController::class, 'create'])->name('keywords.create');
    Route::post('/keywords', [KeywordController::class, 'store'])->name('keywords.store');
    Route::post('/keywords/discover', [KeywordController::class, 'discover'])->name('keywords.discover');
    Route::post('/keywords/{keyword}/generate', [KeywordController::class, 'generate'])->name('keywords.generate');
    Route::post('/keywords/generate-bulk', [KeywordController::class, 'generateBulk'])->name('keywords.generate-bulk');
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    // Articles
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
    Route::patch('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
    Route::post('/articles/{article}/approve', [ArticleController::class, 'approve'])->name('articles.approve');
    Route::post('/articles/{article}/publish', [ArticleController::class, 'publish'])->name('articles.publish');

    // Integrations
    Route::get('/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::get('/integrations/create', [IntegrationController::class, 'create'])->name('integrations.create');
    Route::post('/integrations', [IntegrationController::class, 'store'])->name('integrations.store');
    Route::get('/integrations/{integration}/edit', [IntegrationController::class, 'edit'])->name('integrations.edit');
    Route::patch('/integrations/{integration}', [IntegrationController::class, 'update'])->name('integrations.update');
    Route::patch('/integrations/{integration}/toggle', [IntegrationController::class, 'toggle'])->name('integrations.toggle');
    Route::delete('/integrations/{integration}', [IntegrationController::class, 'destroy'])->name('integrations.destroy');

    // Analytics
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::post('/analytics/{site}/sync', [AnalyticsController::class, 'sync'])->name('analytics.sync');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/billing', [SettingsController::class, 'billing'])->name('settings.billing');
    Route::get('/settings/team', [SettingsController::class, 'team'])->name('settings.team');
    Route::get('/settings/api-keys', [SettingsController::class, 'apiKeys'])->name('settings.api-keys');
    Route::get('/settings/brand-voices', [SettingsController::class, 'brandVoices'])->name('settings.brand-voices');
    Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
