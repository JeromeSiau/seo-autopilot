<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Hosted\HostedPublicController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AnalyticsController;
use App\Http\Controllers\Web\ArticleController;
use App\Http\Controllers\Web\ArticleWorkflowController;
use App\Http\Controllers\Web\BillingController;
use App\Http\Controllers\Web\BrandKitController;
use App\Http\Controllers\Web\CampaignController;
use App\Http\Controllers\Web\IntegrationController;
use App\Http\Controllers\Web\KeywordController;
use App\Http\Controllers\Web\NeedsRefreshController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\OnboardingController;
use App\Http\Controllers\Web\RefreshRecommendationController;
use App\Http\Controllers\Web\ReviewQueueController;
use App\Http\Controllers\Web\SettingsController;
use App\Http\Controllers\Web\SiteController;
use App\Http\Controllers\Web\StripeWebhookController;
use App\Http\Controllers\Web\ContentPlanController;
use App\Http\Controllers\Web\HostedSiteController;
use App\Http\Controllers\Web\TeamController;
use App\Http\Controllers\Web\TeamInvitationController;
use App\Http\Controllers\Web\TeamMemberController;
use App\Http\Controllers\Web\WebhookEndpointController;
use App\Http\Controllers\Webhooks\PloiTenantCertificateController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Landing page with locale detection and redirect
Route::get('/', [HostedPublicController::class, 'root']);
Route::get('/blog', [HostedPublicController::class, 'blog'])->name('hosted.blog');
Route::get('/blog/{slug}', [HostedPublicController::class, 'article'])->name('hosted.article');
Route::get('/authors/{slug}', [HostedPublicController::class, 'author'])->name('hosted.author');
Route::get('/categories/{slug}', [HostedPublicController::class, 'category'])->name('hosted.category');
Route::get('/tags/{slug}', [HostedPublicController::class, 'tag'])->name('hosted.tag');
Route::get('/about', [HostedPublicController::class, 'about'])->name('hosted.about');
Route::get('/legal', [HostedPublicController::class, 'legal'])->name('hosted.legal');
Route::get('/sitemap.xml', [HostedPublicController::class, 'sitemap'])->name('hosted.sitemap');
Route::get('/robots.txt', [HostedPublicController::class, 'robots'])->name('hosted.robots');
Route::get('/feed.xml', [HostedPublicController::class, 'feed'])->name('hosted.feed');

// Localized landing pages
Route::get('/{locale}', [LandingController::class, 'index'])
    ->where('locale', 'en|fr|es')
    ->name('landing');

// Google OAuth
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])
    ->middleware(['auth', 'verified', 'has.team'])
    ->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// User preferences (locale, theme) - accessible to all users
Route::post('/preferences', [PreferencesController::class, 'update'])->name('preferences.update');

// Stripe webhook (outside auth middleware)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');
Route::post('/webhooks/ploi/tenant-certificate', PloiTenantCertificateController::class)
    ->name('webhooks.ploi.tenant-certificate');

// Routes that don't require a team (team creation flow)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/teams/create', [TeamController::class, 'create'])->name('teams.create');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
});

// Routes that require a team
Route::middleware(['auth', 'verified', 'has.team'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Onboarding Wizard
    Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
    Route::get('/onboarding/{site}/resume', [OnboardingController::class, 'resume'])->name('onboarding.resume');
    Route::post('/onboarding/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
    Route::post('/onboarding/{site}/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
    Route::get('/onboarding/{site}/gsc-sites', [OnboardingController::class, 'getGscSites'])->name('onboarding.gsc-sites');
    Route::post('/onboarding/{site}/gsc-property', [OnboardingController::class, 'selectGscProperty'])->name('onboarding.gsc-property');
    Route::get('/onboarding/{site}/ga4-properties', [OnboardingController::class, 'getGa4Properties'])->name('onboarding.ga4-properties');
    Route::post('/onboarding/{site}/ga4-property', [OnboardingController::class, 'selectGa4Property'])->name('onboarding.ga4-property');
    Route::post('/onboarding/{site}/step3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
    Route::post('/onboarding/{site}/step4', [OnboardingController::class, 'storeStep4'])->name('onboarding.step4');
    Route::post('/onboarding/{site}/step5', [OnboardingController::class, 'storeStep5'])->name('onboarding.step5');
    Route::post('/onboarding/{site}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::get('/onboarding/generating/{site}', [OnboardingController::class, 'generating'])->name('onboarding.generating');

    // Content Plan API (JSON endpoints for frontend polling)
    Route::get('/sites/{site}/generation-status', [\App\Http\Controllers\Api\ContentPlanController::class, 'generationStatus'])->name('sites.generation-status');
    Route::get('/sites/{site}/content-plan', [\App\Http\Controllers\Api\ContentPlanController::class, 'contentPlan'])->name('sites.content-plan');

    // Content Plans
    Route::get('/content-plans', [ContentPlanController::class, 'index'])->name('content-plans.index');
    Route::get('/sites/{site}/content-plan-page', [SiteController::class, 'contentPlanPage'])->name('sites.content-plan-page');
    Route::post('/sites/{site}/content-plan/regenerate', [SiteController::class, 'regenerateContentPlan'])->name('sites.content-plan.regenerate');

    // Sites
    Route::resource('sites', SiteController::class);
    Route::get('/sites/{site}/brand-kit', [BrandKitController::class, 'show'])->name('sites.brand-kit.show');
    Route::post('/sites/{site}/brand-kit/import-hosted-pages', [BrandKitController::class, 'importHostedPages'])->name('sites.brand-kit.import-hosted-pages');
    Route::post('/sites/{site}/brand-kit/import-published-articles', [BrandKitController::class, 'importPublishedArticles'])->name('sites.brand-kit.import-published-articles');
    Route::post('/sites/{site}/brand-assets', [BrandKitController::class, 'storeAsset'])->name('sites.brand-assets.store');
    Route::patch('/sites/{site}/brand-assets/{brandAsset}', [BrandKitController::class, 'updateAsset'])->name('sites.brand-assets.update');
    Route::delete('/sites/{site}/brand-assets/{brandAsset}', [BrandKitController::class, 'destroyAsset'])->name('sites.brand-assets.destroy');
    Route::post('/sites/{site}/brand-rules', [BrandKitController::class, 'storeRule'])->name('sites.brand-rules.store');
    Route::patch('/sites/{site}/brand-rules/{brandRule}', [BrandKitController::class, 'updateRule'])->name('sites.brand-rules.update');
    Route::delete('/sites/{site}/brand-rules/{brandRule}', [BrandKitController::class, 'destroyRule'])->name('sites.brand-rules.destroy');
    Route::get('/sites/{site}/hosting', [HostedSiteController::class, 'show'])->name('sites.hosting.show');
    Route::post('/sites/{site}/hosting/provision-staging', [HostedSiteController::class, 'provisionStaging'])->name('sites.hosting.provision-staging');
    Route::post('/sites/{site}/hosting/domain', [HostedSiteController::class, 'storeDomain'])->name('sites.hosting.domain');
    Route::post('/sites/{site}/hosting/verify-dns', [HostedSiteController::class, 'verifyDns'])->name('sites.hosting.verify-dns');
    Route::patch('/sites/{site}/hosting/theme', [HostedSiteController::class, 'updateTheme'])->name('sites.hosting.theme');
    Route::patch('/sites/{site}/hosting/pages/{kind}', [HostedSiteController::class, 'updatePage'])->name('sites.hosting.pages.update');
    Route::post('/sites/{site}/hosting/custom-pages', [HostedSiteController::class, 'storeCustomPage'])->name('sites.hosting.custom-pages.store');
    Route::patch('/sites/{site}/hosting/custom-pages/{hostedPage}', [HostedSiteController::class, 'updateCustomPage'])->name('sites.hosting.custom-pages.update');
    Route::delete('/sites/{site}/hosting/custom-pages/{hostedPage}', [HostedSiteController::class, 'destroyCustomPage'])->name('sites.hosting.custom-pages.destroy');
    Route::post('/sites/{site}/hosting/authors', [HostedSiteController::class, 'storeAuthor'])->name('sites.hosting.authors.store');
    Route::patch('/sites/{site}/hosting/authors/{hostedAuthor}', [HostedSiteController::class, 'updateAuthor'])->name('sites.hosting.authors.update');
    Route::delete('/sites/{site}/hosting/authors/{hostedAuthor}', [HostedSiteController::class, 'destroyAuthor'])->name('sites.hosting.authors.destroy');
    Route::post('/sites/{site}/hosting/categories', [HostedSiteController::class, 'storeCategory'])->name('sites.hosting.categories.store');
    Route::patch('/sites/{site}/hosting/categories/{hostedCategory}', [HostedSiteController::class, 'updateCategory'])->name('sites.hosting.categories.update');
    Route::delete('/sites/{site}/hosting/categories/{hostedCategory}', [HostedSiteController::class, 'destroyCategory'])->name('sites.hosting.categories.destroy');
    Route::post('/sites/{site}/hosting/tags', [HostedSiteController::class, 'storeTag'])->name('sites.hosting.tags.store');
    Route::patch('/sites/{site}/hosting/tags/{hostedTag}', [HostedSiteController::class, 'updateTag'])->name('sites.hosting.tags.update');
    Route::delete('/sites/{site}/hosting/tags/{hostedTag}', [HostedSiteController::class, 'destroyTag'])->name('sites.hosting.tags.destroy');
    Route::post('/sites/{site}/hosting/assets', [HostedSiteController::class, 'storeAsset'])->name('sites.hosting.assets.store');
    Route::patch('/sites/{site}/hosting/assets/{hostedAsset}', [HostedSiteController::class, 'updateAsset'])->name('sites.hosting.assets.update');
    Route::delete('/sites/{site}/hosting/assets/{hostedAsset}', [HostedSiteController::class, 'destroyAsset'])->name('sites.hosting.assets.destroy');
    Route::post('/sites/{site}/hosting/navigation-items', [HostedSiteController::class, 'storeNavigationItem'])->name('sites.hosting.navigation-items.store');
    Route::patch('/sites/{site}/hosting/navigation-items/{hostedNavigationItem}', [HostedSiteController::class, 'updateNavigationItem'])->name('sites.hosting.navigation-items.update');
    Route::delete('/sites/{site}/hosting/navigation-items/{hostedNavigationItem}', [HostedSiteController::class, 'destroyNavigationItem'])->name('sites.hosting.navigation-items.destroy');
    Route::post('/sites/{site}/hosting/redirects', [HostedSiteController::class, 'storeRedirect'])->name('sites.hosting.redirects.store');
    Route::patch('/sites/{site}/hosting/redirects/{hostedRedirect}', [HostedSiteController::class, 'updateRedirect'])->name('sites.hosting.redirects.update');
    Route::delete('/sites/{site}/hosting/redirects/{hostedRedirect}', [HostedSiteController::class, 'destroyRedirect'])->name('sites.hosting.redirects.destroy');
    Route::post('/sites/{site}/exports/site', [HostedSiteController::class, 'exportSite'])->name('sites.export-site');
    Route::get('/sites/{site}/exports/site/download', [HostedSiteController::class, 'downloadSiteExport'])->name('sites.download-site-export');

    // Keywords
    Route::get('/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
    Route::get('/keywords/create', [KeywordController::class, 'create'])->name('keywords.create');
    Route::post('/keywords', [KeywordController::class, 'store'])->name('keywords.store');
    Route::post('/keywords/discover', [KeywordController::class, 'discover'])->name('keywords.discover');
    Route::post('/keywords/{keyword}/generate', [KeywordController::class, 'generate'])->name('keywords.generate');
    Route::post('/keywords/generate-bulk', [KeywordController::class, 'generateBulk'])->name('keywords.generate-bulk');
    Route::delete('/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

    // Articles
    Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('/articles/needs-refresh', [NeedsRefreshController::class, 'index'])->name('articles.needs-refresh');
    Route::get('/articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/review-queue', [ReviewQueueController::class, 'index'])->name('articles.review-queue');
    Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');
    Route::get('/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
    Route::patch('/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
    Route::patch('/articles/{article}/hosted-metadata', [ArticleController::class, 'updateHostedMetadata'])->name('articles.hosted-metadata.update');
    Route::delete('/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');
    Route::post('/articles/{article}/approve', [ArticleController::class, 'approve'])->name('articles.approve');
    Route::post('/articles/{article}/publish', [ArticleController::class, 'publish'])->name('articles.publish');
    Route::post('/articles/{article}/comments', [ArticleWorkflowController::class, 'storeComment'])->name('articles.comments.store');
    Route::patch('/articles/{article}/comments/{editorialComment}/resolve', [ArticleWorkflowController::class, 'resolveComment'])->name('articles.comments.resolve');
    Route::post('/articles/{article}/assignments', [ArticleWorkflowController::class, 'storeAssignment'])->name('articles.assignments.store');
    Route::delete('/articles/{article}/assignments/{articleAssignment}', [ArticleWorkflowController::class, 'destroyAssignment'])->name('articles.assignments.destroy');
    Route::post('/articles/{article}/approval-requests', [ArticleWorkflowController::class, 'requestApproval'])->name('articles.approval-requests.store');
    Route::post('/articles/{article}/approval-requests/{approvalRequest}/approve', [ArticleWorkflowController::class, 'approveRequest'])->name('articles.approval-requests.approve');
    Route::post('/articles/{article}/approval-requests/{approvalRequest}/reject', [ArticleWorkflowController::class, 'rejectRequest'])->name('articles.approval-requests.reject');
    Route::get('/articles/{article}/exports/html', [HostedSiteController::class, 'downloadArticleHtml'])->name('articles.export-html');

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
    Route::get('/analytics/ai-visibility', [AnalyticsController::class, 'aiVisibility'])->name('analytics.ai-visibility.index');
    Route::post('/analytics/{site}/sync', [AnalyticsController::class, 'sync'])->name('analytics.sync');
    Route::post('/analytics/{site}/ai-visibility/sync', [AnalyticsController::class, 'syncAiVisibility'])->name('analytics.ai-visibility.sync');
    Route::post('/analytics/{site}/refresh-detect', [AnalyticsController::class, 'detectRefresh'])->name('analytics.refresh.detect');
    Route::post('/refresh-recommendations/{refreshRecommendation}/accept', [RefreshRecommendationController::class, 'accept'])->name('refresh-recommendations.accept');
    Route::post('/refresh-recommendations/{refreshRecommendation}/dismiss', [RefreshRecommendationController::class, 'dismiss'])->name('refresh-recommendations.dismiss');
    Route::post('/refresh-recommendations/{refreshRecommendation}/execute', [RefreshRecommendationController::class, 'execute'])->name('refresh-recommendations.execute');
    Route::post('/refresh-recommendations/{refreshRecommendation}/apply', [RefreshRecommendationController::class, 'apply'])->name('refresh-recommendations.apply');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/team', [SettingsController::class, 'team'])->name('settings.team');
    Route::get('/settings/api-keys', [SettingsController::class, 'apiKeys'])->name('settings.api-keys');
    Route::get('/settings/notifications', [SettingsController::class, 'notifications'])->name('settings.notifications');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::post('/settings/webhooks', [WebhookEndpointController::class, 'store'])->name('settings.webhooks.store');
    Route::patch('/settings/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'update'])->name('settings.webhooks.update');
    Route::delete('/settings/webhooks/{webhookEndpoint}', [WebhookEndpointController::class, 'destroy'])->name('settings.webhooks.destroy');
    Route::post('/settings/webhooks/{webhookEndpoint}/test', [WebhookEndpointController::class, 'test'])->name('settings.webhooks.test');

    // Billing
    Route::get('/billing', [BillingController::class, 'index'])->name('settings.billing');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');

    // Teams (requires existing team)
    Route::patch('/teams/{team}', [TeamController::class, 'update'])->name('teams.update');
    Route::post('/teams/{team}/switch', [TeamController::class, 'switch'])->name('teams.switch');

    // Team Members
    Route::post('/teams/{team}/members/{user}', [TeamMemberController::class, 'update'])->name('teams.members.update');
    Route::delete('/teams/{team}/members/{user}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');

    // Team Invitations
    Route::post('/teams/{team}/invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
    Route::delete('/teams/{team}/invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Invitation accept - needs to work for non-authenticated users
Route::get('/invitations/{token}/accept', [TeamInvitationController::class, 'accept'])->name('invitations.accept');
Route::get('/{pageSlug}', [HostedPublicController::class, 'customPage'])
    ->where('pageSlug', '[a-z0-9]+(?:-[a-z0-9]+)*')
    ->name('hosted.page');
