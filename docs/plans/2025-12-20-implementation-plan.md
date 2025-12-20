# SEO Autopilot - Plan d'Implémentation

**Date**: 2025-12-20
**Status**: En cours

---

## Progression Actuelle

### Terminé
- [x] Setup Laravel 12 + Inertia + React + TypeScript
- [x] Breeze authentication
- [x] Migrations (teams, sites, keywords, articles, etc.)
- [x] Models Eloquent avec relations
- [x] Service LLM (OpenAI, Anthropic, Google) avec orchestration multi-modèles
- [x] ArticleGenerator avec pipeline 4 étapes
- [x] GenerateArticleJob async
- [x] Service Image (Replicate FLUX)

---

## Phase 1: Services Google (OAuth + APIs)

### 1.1 Google OAuth Service
**Fichiers à créer:**
```
app/Services/Google/
├── GoogleAuthService.php       # OAuth flow, token refresh
├── DTOs/
│   └── GoogleTokens.php        # Access/refresh tokens DTO
```

**Fonctionnalités:**
- OAuth2 flow pour Search Console + GA4
- Stockage tokens encryptés dans `sites` table
- Auto-refresh des tokens expirés
- Scopes: `webmasters.readonly`, `analytics.readonly`

### 1.2 Google Search Console Service
**Fichiers à créer:**
```
app/Services/Google/
├── SearchConsoleService.php    # API wrapper
├── DTOs/
│   ├── SearchAnalyticsRow.php  # Données performance
│   └── SiteInfo.php            # Infos site vérifié
```

**Fonctionnalités:**
- Lister sites vérifiés
- Query performance data (impressions, clicks, position, CTR)
- Filtrage par page/query/date
- Extraction keywords positions 5-30 (quick wins)

### 1.3 Google Analytics 4 Service
**Fichiers à créer:**
```
app/Services/Google/
├── GA4Service.php              # API wrapper
├── DTOs/
│   └── GA4Report.php           # Données analytics
```

**Fonctionnalités:**
- Sessions par page
- Temps moyen sur page
- Bounce rate
- Conversions (si configurées)

---

## Phase 2: Keyword Discovery Engine

### 2.1 DataForSEO Service
**Fichiers à créer:**
```
app/Services/SEO/
├── DataForSEOService.php       # API wrapper
├── DTOs/
│   ├── KeywordData.php         # Volume, difficulty, CPC
│   └── SerpResult.php          # Résultats SERP
```

**Fonctionnalités:**
- Keyword volume lookup
- Difficulty score
- CPC et tendances
- SERP analysis (top 10)

### 2.2 Keyword Discovery Service
**Fichiers à créer:**
```
app/Services/Keyword/
├── KeywordDiscoveryService.php # Orchestration découverte
├── KeywordScoringService.php   # Calcul score priorité
├── TopicClusteringService.php  # Clustering IA
```

**Fonctionnalités:**
- Mining Search Console (positions 5-30)
- Génération topics via LLM
- Scoring composite: volume × difficulty × quick-win × relevance
- Clustering par topic (pillar/cluster)
- Auto-génération calendar 30 jours

### 2.3 Jobs de Découverte
**Fichiers à créer:**
```
app/Jobs/
├── DiscoverKeywordsJob.php     # Job principal
├── EnrichKeywordsJob.php       # Ajout volume/difficulty
├── ClusterKeywordsJob.php      # Clustering IA
```

---

## Phase 3: Publishing Services

### 3.1 Publisher Interface
**Fichiers à créer:**
```
app/Services/Publisher/
├── Contracts/
│   └── PublisherInterface.php  # Interface commune
├── DTOs/
│   ├── PublishRequest.php      # Données article à publier
│   └── PublishResult.php       # URL + ID distant
```

### 3.2 WordPress Publisher
**Fichiers à créer:**
```
app/Services/Publisher/Providers/
├── WordPressPublisher.php      # REST API v2
```

**Fonctionnalités:**
- Auth via Application Password ou JWT
- Upload images (featured + inline)
- Création post (draft/publish)
- Support catégories/tags
- Meta SEO (Yoast/RankMath compatible)

### 3.3 Webflow Publisher
**Fichiers à créer:**
```
app/Services/Publisher/Providers/
├── WebflowPublisher.php        # CMS API
```

**Fonctionnalités:**
- Auth via API Token
- Création items CMS Collection
- Upload images vers assets
- Rich text formatting
- Publish/draft toggle

### 3.4 Shopify Publisher
**Fichiers à créer:**
```
app/Services/Publisher/Providers/
├── ShopifyPublisher.php        # Admin API
```

**Fonctionnalités:**
- Auth via Admin API Token
- Création blog articles
- Upload images
- Metafields SEO

### 3.5 Job de Publication
**Fichiers à créer:**
```
app/Jobs/
├── PublishArticleJob.php       # Publication async
```

**Features:**
- Retry 3× avec exponential backoff
- Upload images en premier
- Mise à jour `published_url` et `published_at`
- Notification si échec

---

## Phase 4: Analytics Sync

### 4.1 Analytics Sync Service
**Fichiers à créer:**
```
app/Services/Analytics/
├── AnalyticsSyncService.php    # Orchestration sync
├── ROICalculatorService.php    # Calcul ROI
```

**Fonctionnalités:**
- Sync quotidien GSC + GA4
- Agrégation par article
- Détection variations position (alertes)
- Calcul ROI: (clics × CPC moyen) vs coût génération

### 4.2 Jobs de Sync
**Fichiers à créer:**
```
app/Jobs/
├── SyncSiteAnalyticsJob.php    # Sync par site
├── GenerateAnalyticsReportJob.php # Rapports périodiques
```

### 4.3 Scheduled Tasks
**À ajouter dans `routes/console.php`:**
```php
Schedule::job(new SyncSiteAnalyticsJob)->daily()->at('03:00');
Schedule::job(new GenerateAnalyticsReportJob)->weekly()->mondays();
```

---

## Phase 5: API Controllers

### 5.1 Controllers Principaux
**Fichiers à créer:**
```
app/Http/Controllers/Api/
├── TeamController.php          # CRUD team + billing
├── SiteController.php          # CRUD sites
├── KeywordController.php       # CRUD + discovery
├── ArticleController.php       # CRUD + génération
├── IntegrationController.php   # CRUD integrations
├── BrandVoiceController.php    # CRUD brand voices
├── AnalyticsController.php     # Données analytics
├── PublishController.php       # Publication articles
```

### 5.2 Auth Controllers
**Fichiers à créer:**
```
app/Http/Controllers/Auth/
├── GoogleAuthController.php    # OAuth Google callback
├── StripeWebhookController.php # Webhooks Stripe
```

### 5.3 Form Requests
**Fichiers à créer:**
```
app/Http/Requests/
├── StoreSiteRequest.php
├── StoreKeywordRequest.php
├── StoreArticleRequest.php
├── StoreIntegrationRequest.php
├── StoreBrandVoiceRequest.php
├── GenerateArticleRequest.php
├── PublishArticleRequest.php
```

### 5.4 Resources (API Responses)
**Fichiers à créer:**
```
app/Http/Resources/
├── TeamResource.php
├── SiteResource.php
├── KeywordResource.php
├── ArticleResource.php
├── IntegrationResource.php
├── BrandVoiceResource.php
├── AnalyticsResource.php
```

### 5.5 Routes API
**Fichier: `routes/api.php`**
```php
Route::middleware('auth:sanctum')->group(function () {
    // Team & Billing
    Route::apiResource('team', TeamController::class)->only(['show', 'update']);
    Route::get('team/billing', [TeamController::class, 'billing']);
    Route::post('team/subscribe', [TeamController::class, 'subscribe']);

    // Sites
    Route::apiResource('sites', SiteController::class);
    Route::post('sites/{site}/connect-gsc', [SiteController::class, 'connectGSC']);
    Route::post('sites/{site}/connect-ga4', [SiteController::class, 'connectGA4']);

    // Keywords
    Route::apiResource('sites/{site}/keywords', KeywordController::class);
    Route::post('sites/{site}/keywords/discover', [KeywordController::class, 'discover']);
    Route::post('sites/{site}/keywords/cluster', [KeywordController::class, 'cluster']);

    // Articles
    Route::apiResource('sites/{site}/articles', ArticleController::class);
    Route::post('articles/{article}/generate', [ArticleController::class, 'generate']);
    Route::post('articles/{article}/regenerate', [ArticleController::class, 'regenerate']);
    Route::post('articles/{article}/publish', [PublishController::class, 'publish']);

    // Integrations
    Route::apiResource('integrations', IntegrationController::class);
    Route::post('integrations/{integration}/test', [IntegrationController::class, 'test']);

    // Brand Voices
    Route::apiResource('brand-voices', BrandVoiceController::class);
    Route::post('brand-voices/analyze', [BrandVoiceController::class, 'analyze']);

    // Analytics
    Route::get('sites/{site}/analytics', [AnalyticsController::class, 'index']);
    Route::get('sites/{site}/analytics/dashboard', [AnalyticsController::class, 'dashboard']);
    Route::get('articles/{article}/analytics', [AnalyticsController::class, 'article']);
});

// Google OAuth
Route::get('auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback']);

// Stripe Webhooks
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle']);
```

---

## Phase 6: Dashboard React

### 6.1 Layout & Components Communs
**Fichiers à créer:**
```
resources/js/
├── Layouts/
│   ├── AuthenticatedLayout.tsx    # Layout principal
│   └── GuestLayout.tsx            # Login/Register
├── Components/
│   ├── Sidebar.tsx                # Navigation
│   ├── Header.tsx                 # Top bar
│   ├── StatsCard.tsx              # KPI cards
│   ├── DataTable.tsx              # Table réutilisable
│   ├── Modal.tsx                  # Modals
│   ├── Button.tsx                 # Boutons
│   ├── Input.tsx                  # Inputs
│   ├── Select.tsx                 # Selects
│   ├── Badge.tsx                  # Status badges
│   └── Chart.tsx                  # Wrapper Recharts
```

### 6.2 Pages Dashboard
**Fichiers à créer:**
```
resources/js/Pages/
├── Dashboard/
│   └── Index.tsx                  # Vue d'ensemble
├── Sites/
│   ├── Index.tsx                  # Liste sites
│   ├── Show.tsx                   # Détail site
│   └── Create.tsx                 # Nouveau site
├── Keywords/
│   ├── Index.tsx                  # Liste keywords
│   ├── Discover.tsx               # Découverte
│   └── Calendar.tsx               # Content calendar (DnD)
├── Articles/
│   ├── Index.tsx                  # Liste articles
│   ├── Show.tsx                   # Détail + preview
│   ├── Edit.tsx                   # Éditeur contenu
│   └── Generate.tsx               # Wizard génération
├── Analytics/
│   ├── Index.tsx                  # Vue analytics
│   └── Article.tsx                # Analytics article
├── Integrations/
│   ├── Index.tsx                  # Liste integrations
│   └── Connect.tsx                # Wizard connexion
├── BrandVoice/
│   ├── Index.tsx                  # Liste brand voices
│   └── Analyze.tsx                # Analyseur
├── Settings/
│   ├── Profile.tsx                # Profil utilisateur
│   ├── Team.tsx                   # Paramètres team
│   └── Billing.tsx                # Facturation Stripe
```

### 6.3 Routes Inertia
**Fichier: `routes/web.php`**
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('sites', SiteController::class);
    Route::get('sites/{site}/keywords', [KeywordController::class, 'index'])->name('keywords.index');
    Route::get('sites/{site}/articles', [ArticleController::class, 'index'])->name('articles.index');
    Route::get('sites/{site}/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    Route::resource('articles', ArticleController::class)->only(['show', 'edit', 'update', 'destroy']);
    Route::resource('integrations', IntegrationController::class);
    Route::resource('brand-voices', BrandVoiceController::class);

    Route::get('settings/profile', [SettingsController::class, 'profile'])->name('settings.profile');
    Route::get('settings/team', [SettingsController::class, 'team'])->name('settings.team');
    Route::get('settings/billing', [SettingsController::class, 'billing'])->name('settings.billing');
});
```

---

## Phase 7: Tests & Polish

### 7.1 Tests Unitaires
**Fichiers à créer:**
```
tests/Unit/
├── Services/
│   ├── LLMManagerTest.php
│   ├── ArticleGeneratorTest.php
│   ├── ImageGeneratorTest.php
│   ├── KeywordDiscoveryTest.php
│   └── PublisherTest.php
├── Models/
│   ├── TeamTest.php
│   ├── ArticleTest.php
│   └── KeywordTest.php
```

### 7.2 Tests Feature
**Fichiers à créer:**
```
tests/Feature/
├── Api/
│   ├── SiteApiTest.php
│   ├── KeywordApiTest.php
│   ├── ArticleApiTest.php
│   └── AnalyticsApiTest.php
├── Jobs/
│   ├── GenerateArticleJobTest.php
│   └── PublishArticleJobTest.php
```

### 7.3 Configuration Production
- `.env.production` template
- Horizon config optimisée
- Redis cache tuning
- Error monitoring (Sentry)
- Log rotation

---

## Ordre d'Exécution Recommandé

```
Phase 1: Google Services     ████░░░░░░ 40%
  └─ 1.1 OAuth               [2h]
  └─ 1.2 Search Console      [3h]
  └─ 1.3 GA4                 [2h]

Phase 2: Keyword Discovery   ░░░░░░░░░░ 0%
  └─ 2.1 DataForSEO          [2h]
  └─ 2.2 Discovery Service   [4h]
  └─ 2.3 Jobs                [2h]

Phase 3: Publishers          ░░░░░░░░░░ 0%
  └─ 3.1-3.2 WordPress       [4h]
  └─ 3.3 Webflow             [3h]
  └─ 3.4 Shopify             [3h]
  └─ 3.5 Job                 [1h]

Phase 4: Analytics           ░░░░░░░░░░ 0%
  └─ 4.1 Sync Service        [3h]
  └─ 4.2-4.3 Jobs            [2h]

Phase 5: API Controllers     ░░░░░░░░░░ 0%
  └─ 5.1-5.4 Controllers     [6h]
  └─ 5.5 Routes              [1h]

Phase 6: React Dashboard     ░░░░░░░░░░ 0%
  └─ 6.1 Layout/Components   [4h]
  └─ 6.2-6.3 Pages           [12h]

Phase 7: Tests & Polish      ░░░░░░░░░░ 0%
  └─ 7.1-7.2 Tests           [6h]
  └─ 7.3 Config prod         [2h]
```

**Temps total estimé: ~60h de développement**

---

*Plan créé le 2025-12-20*
