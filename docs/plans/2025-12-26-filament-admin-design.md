# Filament Admin Panel Design

## Overview

Admin panel personnel pour gérer les clients, plans, sites, articles et visualiser les stats business/ops.

**Stack:** Filament v4, Laravel 12
**URL:** `/ctrl-56389305`
**Auth:** Champ `is_admin` sur User

## Decisions

| Question | Choix |
|----------|-------|
| Statut clients | Basé sur Stripe (Trial/Actif/Inactif) |
| Cost tracking | Table `cost_logs` polymorphique complète |
| Auth admin | Champ `is_admin` booléen sur User |
| CRUDs | Teams, Plans, Sites, Articles, Keywords, Integrations, ScheduledArticles, CostLogs, AutopilotLogs |
| Stats | Toutes (Business + Ops) |
| URL | `/ctrl-56389305` |

---

## Architecture

### Installation

```bash
composer require filament/filament:"^4.0"
php artisan filament:install --panels
```

### Structure des fichiers

```
app/
├── Filament/
│   ├── Pages/
│   │   └── Dashboard.php
│   ├── Resources/
│   │   ├── TeamResource.php
│   │   ├── PlanResource.php
│   │   ├── SiteResource.php
│   │   ├── ArticleResource.php
│   │   ├── KeywordResource.php
│   │   ├── IntegrationResource.php
│   │   ├── ScheduledArticleResource.php
│   │   ├── CostLogResource.php
│   │   └── AutopilotLogResource.php
│   └── Widgets/
│       ├── StatsOverviewWidget.php
│       ├── ArticlesChartWidget.php
│       ├── ClientDistributionWidget.php
│       ├── RevenueChartWidget.php
│       ├── CostBreakdownWidget.php
│       ├── TopClientsWidget.php
│       └── RecentFailuresWidget.php
├── Providers/
│   └── Filament/
│       └── AdminPanelProvider.php
├── Models/
│   └── CostLog.php
└── Services/
    └── CostTracker.php
```

### Migrations

#### 1. Add `is_admin` to users

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_admin')->default(false)->after('theme');
});
```

#### 2. Create `cost_logs` table

```php
Schema::create('cost_logs', function (Blueprint $table) {
    $table->id();
    $table->morphs('costable');           // article_id, site_id, etc.
    $table->foreignId('team_id')->constrained();
    $table->string('type');               // llm, image, api
    $table->string('provider');           // openrouter, replicate, dataforseo
    $table->string('model')->nullable();  // claude-sonnet-4, flux-1.1-pro
    $table->string('operation');          // research, outline, write_section, featured_image
    $table->decimal('cost', 10, 6);       // Précision pour micro-coûts
    $table->integer('input_tokens')->nullable();
    $table->integer('output_tokens')->nullable();
    $table->json('metadata')->nullable();
    $table->timestamps();

    $table->index(['team_id', 'created_at']);
    $table->index(['type', 'created_at']);
});
```

---

## AdminPanelProvider Configuration

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Models\User;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('ctrl-56389305')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                // ...
            ])
            ->authMiddleware([
                // ...
            ])
            ->authGuard('web');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return auth()->user()?->is_admin ?? false;
    }
}
```

---

## Cost Tracking System

### Types de coûts trackés

| Type | Provider | Operations |
|------|----------|------------|
| `llm` | openrouter | research, outline, write_section, polish, image_prompt, topic_analysis, duplicate_check, keyword_enrichment |
| `image` | replicate | featured_image, section_image |
| `api` | dataforseo | keyword_data, serp_analysis |

### CostLog Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CostLog extends Model
{
    protected $fillable = [
        'costable_type',
        'costable_id',
        'team_id',
        'type',
        'provider',
        'model',
        'operation',
        'cost',
        'input_tokens',
        'output_tokens',
        'metadata',
    ];

    protected $casts = [
        'cost' => 'decimal:6',
        'metadata' => 'array',
    ];

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('created_at', now()->month)
                     ->whereYear('created_at', now()->year);
    }

    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    // Accessors
    public function getFormattedCostAttribute(): string
    {
        return '€' . number_format($this->cost, 4);
    }
}
```

### CostTracker Service

```php
<?php

namespace App\Services;

use App\Models\CostLog;
use Illuminate\Database\Eloquent\Model;

class CostTracker
{
    public static function log(
        Model $costable,
        string $type,
        string $provider,
        string $operation,
        float $cost,
        ?string $model = null,
        ?int $inputTokens = null,
        ?int $outputTokens = null,
        array $metadata = []
    ): CostLog {
        $teamId = self::resolveTeamId($costable);

        return CostLog::create([
            'costable_type' => $costable->getMorphClass(),
            'costable_id' => $costable->id,
            'team_id' => $teamId,
            'type' => $type,
            'provider' => $provider,
            'model' => $model,
            'operation' => $operation,
            'cost' => $cost,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'metadata' => $metadata,
        ]);
    }

    private static function resolveTeamId(Model $costable): int
    {
        if ($costable instanceof \App\Models\Article) {
            return $costable->site->team_id;
        }

        if ($costable instanceof \App\Models\Site) {
            return $costable->team_id;
        }

        if ($costable instanceof \App\Models\Team) {
            return $costable->id;
        }

        throw new \InvalidArgumentException('Cannot resolve team_id from costable');
    }
}
```

### Integration dans LLMManager

```php
// Dans LLMManager.php
private ?Model $costable = null;
private ?string $currentOperation = null;

public function withCostable(Model $costable): self
{
    $this->costable = $costable;
    return $this;
}

public function withOperation(string $operation): self
{
    $this->currentOperation = $operation;
    return $this;
}

public function complete(string $provider, string $prompt, array $options = []): LLMResponse
{
    $response = $this->getProvider($provider)->complete($prompt, $options);

    $this->trackCost($provider, $response->model, $response->cost);

    if ($this->costable) {
        CostTracker::log(
            costable: $this->costable,
            type: 'llm',
            provider: $provider,
            operation: $this->currentOperation ?? 'unknown',
            cost: $response->cost,
            model: $response->model,
            inputTokens: $response->inputTokens ?? null,
            outputTokens: $response->outputTokens ?? null,
        );
    }

    return $response;
}
```

---

## Dashboard Stats

### Layout

```
┌─────────────────────────────────────────────────────────────────┐
│  STATS OVERVIEW (4 cards)                                       │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
│  │ Articles │ │ Clients  │ │   MRR    │ │  Coût    │           │
│  │   142    │ │    23    │ │  €1,840  │ │  €234    │           │
│  │ +12 7j   │ │ 5T/15A/3I│ │ +€200    │ │ ce mois  │           │
│  └──────────┘ └──────────┘ └──────────┘ └──────────┘           │
├─────────────────────────────────────────────────────────────────┤
│  ARTICLES CHART (30 jours)          │  CLIENT DISTRIBUTION      │
│  ▁▂▃▅▇█▆▄▃▂▁▂▃▄▅▆▇█▆▅▄▃▂▁          │  ██████ Trial (5)        │
│  Generated | Published | Failed      │  ████████████ Actif (15) │
│                                      │  ███ Inactif (3)         │
├─────────────────────────────────────────────────────────────────┤
│  REVENUE CHART (12 mois)            │  COST BREAKDOWN           │
│  MRR trend + projection             │  LLM: €180 (77%)          │
│                                      │  Images: €42 (18%)        │
│                                      │  APIs: €12 (5%)           │
├─────────────────────────────────────────────────────────────────┤
│  TOP CLIENTS                        │  RECENT FAILURES          │
│  1. Client A - 45 articles          │  ⚠ Article X - timeout    │
│  2. Client B - 32 articles          │  ⚠ Article Y - rate limit │
└─────────────────────────────────────────────────────────────────┘
```

### Widgets

| Widget | Type | Données |
|--------|------|---------|
| `StatsOverviewWidget` | Stats cards | Articles total, Clients (T/A/I), MRR, Coût mois |
| `ArticlesChartWidget` | Line chart | Articles/jour sur 30j (generated/published/failed) |
| `ClientDistributionWidget` | Pie chart | Trial/Actif/Inactif |
| `RevenueChartWidget` | Line chart | MRR 12 mois + trend |
| `CostBreakdownWidget` | Pie chart | Par type (LLM/Image/API) |
| `TopClientsWidget` | Table | Top 10 par articles générés |
| `RecentFailuresWidget` | Table | 10 derniers articles failed |

### Calculs clés

```php
// MRR - Monthly Recurring Revenue
$mrr = Team::whereHas('subscriptions', fn($q) => $q->active())
    ->with('billingPlan')
    ->get()
    ->sum('billingPlan.price');

// Churn Rate
$cancelledThisMonth = Team::whereHas('subscriptions', fn($q) =>
    $q->where('ends_at', '>=', now()->startOfMonth())
      ->where('ends_at', '<=', now()->endOfMonth())
)->count();
$activeLastMonth = Team::whereHas('subscriptions', fn($q) =>
    $q->where('created_at', '<', now()->startOfMonth())
)->count();
$churnRate = $activeLastMonth > 0 ? ($cancelledThisMonth / $activeLastMonth) * 100 : 0;

// LTV - Lifetime Value
$avgMrr = $mrr / max(Team::whereHas('subscriptions')->count(), 1);
$avgLifetimeMonths = 12; // À calculer basé sur données réelles
$ltv = $avgMrr * $avgLifetimeMonths;

// Taux d'échec génération
$totalArticles = Article::count();
$failedArticles = Article::where('status', 'failed')->count();
$failureRate = $totalArticles > 0 ? ($failedArticles / $totalArticles) * 100 : 0;

// Temps moyen de génération
$avgGenerationTime = Article::whereNotNull('generation_time_seconds')
    ->avg('generation_time_seconds');

// Coût moyen par article
$avgCostPerArticle = CostLog::where('costable_type', Article::class)
    ->avg('cost');
```

---

## CRUD Resources

### TeamResource (Clients)

```php
// Table columns
TextColumn::make('name')->searchable(),
TextColumn::make('owner.email')->searchable(),
TextColumn::make('billingPlan.name')->badge(),
TextColumn::make('status')->badge()->state(fn($record) => match(true) {
    $record->is_trial && !$record->isTrialExpired() => 'trial',
    $record->subscribed() => 'active',
    default => 'inactive',
}),
TextColumn::make('sites_count')->counts('sites'),
TextColumn::make('articles_used_this_month'),
TextColumn::make('created_at')->dateTime(),

// Filters
SelectFilter::make('status'),
SelectFilter::make('plan_id')->relationship('billingPlan', 'name'),

// Actions
Action::make('stripe_portal')->url(fn($record) => route('billing.portal', $record)),
Action::make('impersonate'),
```

### PlanResource

```php
// Table
TextColumn::make('name'),
TextColumn::make('slug'),
TextColumn::make('price')->money('EUR'),
TextColumn::make('articles_per_month'),
TextColumn::make('sites_limit'),
TextColumn::make('teams_count')->counts('teams'),
ToggleColumn::make('is_active'),

// Form
TextInput::make('name')->required(),
TextInput::make('slug')->required(),
TextInput::make('price')->numeric()->required(),
TextInput::make('articles_per_month')->numeric()->required(),
TextInput::make('sites_limit')->numeric()->required(),
TextInput::make('stripe_price_id_live'),
TextInput::make('stripe_price_id_test'),
Repeater::make('features')->schema([
    TextInput::make('feature'),
]),
Toggle::make('is_active'),
TextInput::make('sort_order')->numeric(),
```

### SiteResource

```php
// Table
TextColumn::make('domain')->searchable(),
TextColumn::make('team.name'),
TextColumn::make('language'),
TextColumn::make('articles_count')->counts('articles'),
IconColumn::make('settings.autopilot_enabled')->boolean(),
IconColumn::make('gsc_connected')->boolean(),
TextColumn::make('created_at')->dateTime(),

// Filters
SelectFilter::make('team_id')->relationship('team', 'name'),
SelectFilter::make('language'),
TernaryFilter::make('autopilot_enabled'),
TernaryFilter::make('gsc_connected'),

// Relation Managers
ArticlesRelationManager::class,
KeywordsRelationManager::class,
```

### ArticleResource

```php
// Table
TextColumn::make('title')->searchable()->limit(50),
TextColumn::make('site.domain'),
BadgeColumn::make('status')->colors([
    'warning' => 'generating',
    'success' => ['ready', 'published'],
    'danger' => 'failed',
]),
TextColumn::make('word_count'),
TextColumn::make('generation_cost')->money('EUR', 4),
TextColumn::make('llm_used'),
TextColumn::make('created_at')->dateTime(),

// Filters
SelectFilter::make('status'),
SelectFilter::make('site_id')->relationship('site', 'domain'),
Filter::make('created_at')->form([DatePicker::make('from'), DatePicker::make('until')]),

// Tabs
Tabs::make()->tabs([
    Tab::make('All'),
    Tab::make('Generating')->modifyQueryUsing(fn($q) => $q->generating()),
    Tab::make('Ready')->modifyQueryUsing(fn($q) => $q->ready()),
    Tab::make('Published')->modifyQueryUsing(fn($q) => $q->published()),
    Tab::make('Failed')->modifyQueryUsing(fn($q) => $q->failed()),
]),

// Actions
Action::make('view_content')->modalContent(fn($record) => view('filament.article-preview', ['article' => $record])),
Action::make('regenerate'),
Action::make('force_publish'),
```

### KeywordResource

```php
// Table
TextColumn::make('keyword')->searchable(),
TextColumn::make('site.domain'),
BadgeColumn::make('status'),
TextColumn::make('volume'),
TextColumn::make('difficulty'),
TextColumn::make('score'),
TextColumn::make('article.title')->url(fn($record) => $record->article ? ArticleResource::getUrl('edit', ['record' => $record->article]) : null),

// Actions
Action::make('generate_article'),
Action::make('reset_status'),
```

### IntegrationResource

```php
// Table
TextColumn::make('site.domain'),
BadgeColumn::make('type'),
IconColumn::make('is_connected')->boolean(),
TextColumn::make('last_used_at')->dateTime(),

// Actions
Action::make('test_connection'),
Action::make('refresh_token'),
```

### ScheduledArticleResource

```php
// Table
TextColumn::make('keyword.keyword'),
TextColumn::make('site.domain'),
TextColumn::make('scheduled_for')->dateTime(),
BadgeColumn::make('status'),

// Actions
Action::make('reschedule'),
Action::make('cancel'),
Action::make('generate_now'),
```

### CostLogResource

```php
// Table
TextColumn::make('costable_type'),
TextColumn::make('operation'),
TextColumn::make('provider'),
TextColumn::make('model'),
TextColumn::make('cost')->money('EUR', 6),
TextColumn::make('input_tokens'),
TextColumn::make('output_tokens'),
TextColumn::make('created_at')->dateTime(),

// Filters
SelectFilter::make('type'),
SelectFilter::make('provider'),
Filter::make('created_at')->form([DatePicker::make('from'), DatePicker::make('until')]),

// Summaries
Summarizer::make()->sum('cost'),
```

### AutopilotLogResource

```php
// Table
TextColumn::make('site.domain'),
BadgeColumn::make('status'),
TextColumn::make('articles_generated'),
TextColumn::make('errors_count'),
TextColumn::make('ran_at')->dateTime(),

// Actions
Action::make('view_details')->modalContent(fn($record) => view('filament.autopilot-log', ['log' => $record])),
```

---

## Services à modifier pour cost tracking

| Service | Modification |
|---------|--------------|
| `LLMManager` | Ajouter `withCostable()`, `withOperation()`, logger après chaque call |
| `ArticleGenerator` | Passer l'Article via `withCostable()` |
| `ImageGenerator` | Logger coût image + coût LLM prompt via CostTracker |
| `KeywordDiscoveryService` | Logger avec Site comme costable |
| `TopicAnalyzerService` | Logger avec Site comme costable |
| `DuplicateCheckerService` | Logger avec Site comme costable |
| `DataForSEOService` | Logger appels API externes |

---

## UserSeeder Update

```php
public function run(): void
{
    $user = User::factory()->create([
        'name' => 'Jérôme Siau',
        'email' => 'siau.jerome@gmail.com',
        'password' => Hash::make('password'),
        'is_admin' => true, // <-- Ajouter
    ]);

    // ...
}
```

---

## Implementation Order

1. Migrations (`is_admin`, `cost_logs`)
2. Models (`CostLog`)
3. Services (`CostTracker`)
4. Installer Filament v4
5. Configurer `AdminPanelProvider`
6. Dashboard + Widgets
7. Resources (CRUDs)
8. Intégrer cost tracking dans services existants
9. Update UserSeeder
