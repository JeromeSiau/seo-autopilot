# Filament Admin Panel Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Filament v4 admin panel at `/ctrl-56389305` for managing clients, plans, sites, articles with full cost tracking and business/ops stats.

**Architecture:** Filament v4 panel with custom Dashboard, 9 Resources (CRUDs), 7 Widgets. Polymorphic `cost_logs` table tracks all LLM/image/API costs. Auth via `is_admin` boolean on User model.

**Tech Stack:** Laravel 12, Filament v4, Laravel Cashier (Stripe), Chart.js via Filament

---

## Task 1: Install Filament v4 & Create Migrations

**Files:**
- Create: `database/migrations/XXXX_add_is_admin_to_users_table.php`
- Create: `database/migrations/XXXX_create_cost_logs_table.php`

**Step 1: Install Filament v4**

Run:
```bash
composer require filament/filament:"^4.0"
```

Expected: Package installed successfully

**Step 2: Install Filament panels**

Run:
```bash
php artisan filament:install --panels
```

Expected: AdminPanelProvider created at `app/Providers/Filament/AdminPanelProvider.php`

**Step 3: Create is_admin migration**

Run:
```bash
php artisan make:migration add_is_admin_to_users_table
```

Then edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false)->after('theme');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
```

**Step 4: Create cost_logs migration**

Run:
```bash
php artisan make:migration create_cost_logs_table
```

Then edit the migration:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('costable');
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('operation');
            $table->decimal('cost', 10, 6);
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['costable_type', 'costable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_logs');
    }
};
```

**Step 5: Run migrations**

Run:
```bash
php artisan migrate
```

Expected: Both migrations run successfully

**Step 6: Commit**

```bash
git add -A && git commit -m "feat(admin): install Filament v4 and add migrations"
```

---

## Task 2: Create CostLog Model & CostTracker Service

**Files:**
- Create: `app/Models/CostLog.php`
- Create: `app/Services/CostTracker.php`

**Step 1: Create CostLog model**

Create `app/Models/CostLog.php`:

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
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
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

    public function getFormattedCostAttribute(): string
    {
        return '€' . number_format($this->cost, 4);
    }
}
```

**Step 2: Create CostTracker service**

Create `app/Services/CostTracker.php`:

```php
<?php

namespace App\Services;

use App\Models\Article;
use App\Models\CostLog;
use App\Models\Site;
use App\Models\Team;
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
        if ($costable instanceof Article) {
            return $costable->site->team_id;
        }

        if ($costable instanceof Site) {
            return $costable->team_id;
        }

        if ($costable instanceof Team) {
            return $costable->id;
        }

        if (method_exists($costable, 'team')) {
            return $costable->team->id;
        }

        throw new \InvalidArgumentException('Cannot resolve team_id from costable: ' . get_class($costable));
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add CostLog model and CostTracker service"
```

---

## Task 3: Configure AdminPanelProvider

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `app/Models/User.php`

**Step 1: Update User model with is_admin and Filament interface**

Edit `app/Models/User.php`, add to fillable and implement FilamentUser:

```php
<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
// ... existing imports

class User extends Authenticatable implements FilamentUser
{
    // ... existing code

    protected $fillable = [
        'name',
        'email',
        'password',
        'team_id',
        'notification_email_frequency',
        'notification_immediate_failures',
        'notification_immediate_quota',
        'locale',
        'theme',
        'is_admin', // Add this
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notification_immediate_failures' => 'boolean',
            'notification_immediate_quota' => 'boolean',
            'is_admin' => 'boolean', // Add this
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }

    // ... rest of existing code
}
```

**Step 2: Configure AdminPanelProvider**

Replace content of `app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('ctrl-56389305')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
                'danger' => Color::Rose,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->brandName('SEO Autopilot Admin')
            ->favicon(asset('favicon.ico'))
            ->sidebarCollapsibleOnDesktop();
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): configure AdminPanelProvider with auth"
```

---

## Task 4: Update UserSeeder & Test Login

**Files:**
- Modify: `database/seeders/UserSeeder.php`

**Step 1: Update UserSeeder**

Edit `database/seeders/UserSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Jérôme Siau',
            'email' => 'siau.jerome@gmail.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        $team = Team::create([
            'name' => 'Personal Team',
            'owner_id' => $user->id,
            'plan' => 'pro',
            'articles_limit' => 30,
        ]);

        $user->update(['team_id' => $team->id]);
    }
}
```

**Step 2: Refresh database and test**

Run:
```bash
php artisan migrate:fresh --seed
```

**Step 3: Test admin access**

Run:
```bash
php artisan serve
```

Visit `http://localhost:8000/ctrl-56389305` and login with `siau.jerome@gmail.com` / `password`

Expected: Filament dashboard loads successfully

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(admin): update UserSeeder with is_admin flag"
```

---

## Task 5: Create Dashboard Widgets - Stats Overview

**Files:**
- Create: `app/Filament/Widgets/StatsOverviewWidget.php`

**Step 1: Create StatsOverviewWidget**

Create `app/Filament/Widgets/StatsOverviewWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\CostLog;
use App\Models\Team;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $articlesTotal = Article::count();
        $articlesLast7Days = Article::where('created_at', '>=', now()->subDays(7))->count();

        $teamsTotal = Team::count();
        $teamsTrial = Team::where('is_trial', true)
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            })->count();
        $teamsActive = Team::whereHas('subscriptions', fn($q) => $q->active())->count();
        $teamsInactive = $teamsTotal - $teamsTrial - $teamsActive;

        $mrr = Team::whereHas('subscriptions', fn($q) => $q->active())
            ->with('billingPlan')
            ->get()
            ->sum(fn($team) => $team->billingPlan?->price ?? 0);

        $costThisMonth = CostLog::thisMonth()->sum('cost');

        return [
            Stat::make('Articles', $articlesTotal)
                ->description("+{$articlesLast7Days} last 7 days")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Clients', $teamsTotal)
                ->description("{$teamsTrial}T / {$teamsActive}A / {$teamsInactive}I")
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('MRR', '€' . number_format($mrr, 0))
                ->description('Monthly recurring revenue')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Costs', '€' . number_format($costThisMonth, 2))
                ->description('This month')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('warning'),
        ];
    }
}
```

**Step 2: Commit**

```bash
git add -A && git commit -m "feat(admin): add StatsOverviewWidget"
```

---

## Task 6: Create Dashboard Widgets - Charts

**Files:**
- Create: `app/Filament/Widgets/ArticlesChartWidget.php`
- Create: `app/Filament/Widgets/ClientDistributionWidget.php`
- Create: `app/Filament/Widgets/CostBreakdownWidget.php`

**Step 1: Create ArticlesChartWidget**

Create `app/Filament/Widgets/ArticlesChartWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ArticlesChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Articles (30 days)';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn($i) => now()->subDays($i)->format('Y-m-d'));

        $generated = Article::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $published = Article::where('status', 'published')
            ->where('published_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(published_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $failed = Article::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Generated',
                    'data' => $days->map(fn($d) => $generated[$d] ?? 0)->values(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                ],
                [
                    'label' => 'Published',
                    'data' => $days->map(fn($d) => $published[$d] ?? 0)->values(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Failed',
                    'data' => $days->map(fn($d) => $failed[$d] ?? 0)->values(),
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                ],
            ],
            'labels' => $days->map(fn($d) => Carbon::parse($d)->format('M d'))->values(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
```

**Step 2: Create ClientDistributionWidget**

Create `app/Filament/Widgets/ClientDistributionWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Widgets\ChartWidget;

class ClientDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Client Distribution';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $trial = Team::where('is_trial', true)
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            })->count();

        $active = Team::whereHas('subscriptions', fn($q) => $q->active())->count();

        $inactive = Team::count() - $trial - $active;

        return [
            'datasets' => [
                [
                    'data' => [$trial, $active, $inactive],
                    'backgroundColor' => ['#f59e0b', '#10b981', '#6b7280'],
                ],
            ],
            'labels' => ["Trial ({$trial})", "Active ({$active})", "Inactive ({$inactive})"],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
```

**Step 3: Create CostBreakdownWidget**

Create `app/Filament/Widgets/CostBreakdownWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\CostLog;
use Filament\Widgets\ChartWidget;

class CostBreakdownWidget extends ChartWidget
{
    protected static ?string $heading = 'Cost Breakdown (This Month)';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $costs = CostLog::thisMonth()
            ->selectRaw('type, SUM(cost) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $llm = $costs['llm'] ?? 0;
        $image = $costs['image'] ?? 0;
        $api = $costs['api'] ?? 0;

        return [
            'datasets' => [
                [
                    'data' => [$llm, $image, $api],
                    'backgroundColor' => ['#6366f1', '#8b5cf6', '#ec4899'],
                ],
            ],
            'labels' => [
                "LLM (€" . number_format($llm, 2) . ")",
                "Images (€" . number_format($image, 2) . ")",
                "APIs (€" . number_format($api, 2) . ")",
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(admin): add chart widgets for dashboard"
```

---

## Task 7: Create Dashboard Widgets - Tables

**Files:**
- Create: `app/Filament/Widgets/TopClientsWidget.php`
- Create: `app/Filament/Widgets/RecentFailuresWidget.php`

**Step 1: Create TopClientsWidget**

Create `app/Filament/Widgets/TopClientsWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopClientsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Clients (This Month)';
    protected static ?int $sort = 5;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Team::query()
                    ->withCount(['sites as articles_count' => function ($query) {
                        $query->join('articles', 'sites.id', '=', 'articles.site_id')
                            ->whereMonth('articles.created_at', now()->month)
                            ->whereYear('articles.created_at', now()->year);
                    }])
                    ->orderByDesc('articles_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Client'),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles')
                    ->badge()
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
```

**Step 2: Create RecentFailuresWidget**

Create `app/Filament/Widgets/RecentFailuresWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentFailuresWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Failures';
    protected static ?int $sort = 6;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Article::query()
                    ->where('status', 'failed')
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->limit(30)
                    ->tooltip(fn($record) => $record->title),
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(20)
                    ->tooltip(fn($record) => $record->error_message)
                    ->color('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('When'),
            ])
            ->paginated(false);
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add table widgets for dashboard"
```

---

## Task 8: Create TeamResource (Clients CRUD)

**Files:**
- Create: `app/Filament/Resources/TeamResource.php`
- Create: `app/Filament/Resources/TeamResource/Pages/ListTeams.php`
- Create: `app/Filament/Resources/TeamResource/Pages/CreateTeam.php`
- Create: `app/Filament/Resources/TeamResource/Pages/EditTeam.php`

**Step 1: Generate TeamResource**

Run:
```bash
php artisan make:filament-resource Team --generate
```

**Step 2: Replace TeamResource content**

Replace `app/Filament/Resources/TeamResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TeamResource\Pages;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class TeamResource extends Resource
{
    protected static ?string $model = Team::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Clients';
    protected static ?string $modelLabel = 'Client';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Client Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('owner_id')
                            ->relationship('owner', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('plan_id')
                            ->relationship('billingPlan', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Forms\Components\Section::make('Trial & Limits')
                    ->schema([
                        Forms\Components\Toggle::make('is_trial')
                            ->default(true),
                        Forms\Components\DateTimePicker::make('trial_ends_at'),
                        Forms\Components\TextInput::make('articles_limit')
                            ->numeric()
                            ->default(10),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('billingPlan.name')
                    ->badge()
                    ->label('Plan'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(fn(Team $record): string => match (true) {
                        $record->is_trial && !$record->isTrialExpired() => 'trial',
                        $record->subscribed() => 'active',
                        default => 'inactive',
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'inactive' => 'gray',
                    }),
                Tables\Columns\TextColumn::make('sites_count')
                    ->counts('sites')
                    ->label('Sites'),
                Tables\Columns\TextColumn::make('articles_used_this_month')
                    ->label('Articles/Month'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'trial' => $query->where('is_trial', true)->where(fn($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now())),
                            'active' => $query->whereHas('subscriptions', fn($q) => $q->active()),
                            'inactive' => $query->where('is_trial', false)->whereDoesntHave('subscriptions', fn($q) => $q->active()),
                            default => $query,
                        };
                    }),
                Tables\Filters\SelectFilter::make('plan_id')
                    ->relationship('billingPlan', 'name')
                    ->label('Plan'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn(Team $record) => "https://dashboard.stripe.com/customers/{$record->stripe_id}")
                    ->openUrlInNewTab()
                    ->visible(fn(Team $record) => $record->stripe_id),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add TeamResource CRUD"
```

---

## Task 9: Create PlanResource

**Files:**
- Create: `app/Filament/Resources/PlanResource.php` + Pages

**Step 1: Generate PlanResource**

Run:
```bash
php artisan make:filament-resource Plan --generate
```

**Step 2: Replace PlanResource content**

Replace `app/Filament/Resources/PlanResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlanResource\Pages;
use App\Models\Plan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PlanResource extends Resource
{
    protected static ?string $model = Plan::class;
    protected static ?string $navigationIcon = 'heroicon-o-credit-card';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Plan Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                Forms\Components\Section::make('Limits')
                    ->schema([
                        Forms\Components\TextInput::make('articles_per_month')
                            ->required()
                            ->numeric(),
                        Forms\Components\TextInput::make('sites_limit')
                            ->required()
                            ->numeric()
                            ->helperText('-1 for unlimited'),
                    ])->columns(2),

                Forms\Components\Section::make('Stripe')
                    ->schema([
                        Forms\Components\TextInput::make('stripe_price_id_live')
                            ->label('Stripe Price ID (Live)')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('stripe_price_id_test')
                            ->label('Stripe Price ID (Test)')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Features')
                    ->schema([
                        Forms\Components\Repeater::make('features')
                            ->simple(
                                Forms\Components\TextInput::make('feature')
                                    ->required()
                            )
                            ->defaultItems(0),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable(),
                Tables\Columns\TextColumn::make('price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('articles_per_month')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sites_limit')
                    ->formatStateUsing(fn($state) => $state === -1 ? 'Unlimited' : $state),
                Tables\Columns\TextColumn::make('teams_count')
                    ->counts('teams')
                    ->label('Clients'),
                Tables\Columns\ToggleColumn::make('is_active'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPlans::route('/'),
            'create' => Pages\CreatePlan::route('/create'),
            'edit' => Pages\EditPlan::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add PlanResource CRUD"
```

---

## Task 10: Create SiteResource

**Files:**
- Create: `app/Filament/Resources/SiteResource.php` + Pages

**Step 1: Generate SiteResource**

Run:
```bash
php artisan make:filament-resource Site --generate
```

**Step 2: Replace SiteResource content**

Replace `app/Filament/Resources/SiteResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiteResource\Pages;
use App\Models\Site;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SiteResource extends Resource
{
    protected static ?string $model = Site::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Site Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('domain')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('team_id')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('language')
                            ->options([
                                'en' => 'English',
                                'fr' => 'French',
                                'es' => 'Spanish',
                                'de' => 'German',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Content Settings')
                    ->schema([
                        Forms\Components\Textarea::make('business_description')
                            ->rows(3),
                        Forms\Components\Textarea::make('target_audience')
                            ->rows(2),
                        Forms\Components\TagsInput::make('topics'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->sortable()
                    ->url(fn(Site $record) => "https://{$record->domain}", shouldOpenInNewTab: true),
                Tables\Columns\TextColumn::make('team.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('language')
                    ->badge(),
                Tables\Columns\TextColumn::make('articles_count')
                    ->counts('articles')
                    ->label('Articles'),
                Tables\Columns\IconColumn::make('settings.autopilot_enabled')
                    ->boolean()
                    ->label('Autopilot'),
                Tables\Columns\IconColumn::make('gsc_connected')
                    ->boolean()
                    ->label('GSC'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('team_id')
                    ->relationship('team', 'name')
                    ->label('Client'),
                Tables\Filters\SelectFilter::make('language'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_frontend')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Site $record) => route('sites.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit' => Pages\EditSite::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add SiteResource CRUD"
```

---

## Task 11: Create ArticleResource

**Files:**
- Create: `app/Filament/Resources/ArticleResource.php` + Pages

**Step 1: Generate ArticleResource**

Run:
```bash
php artisan make:filament-resource Article --generate
```

**Step 2: Replace ArticleResource content**

Replace `app/Filament/Resources/ArticleResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArticleResource\Pages;
use App\Models\Article;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArticleResource extends Resource
{
    protected static ?string $model = Article::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Information')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('slug')
                            ->maxLength(255),
                        Forms\Components\Select::make('site_id')
                            ->relationship('site', 'domain')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'generating' => 'Generating',
                                'ready' => 'Ready',
                                'published' => 'Published',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Content')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('SEO')
                    ->schema([
                        Forms\Components\TextInput::make('meta_title')
                            ->maxLength(60),
                        Forms\Components\Textarea::make('meta_description')
                            ->maxLength(160)
                            ->rows(2),
                    ])->columns(2),

                Forms\Components\Section::make('Generation Info')
                    ->schema([
                        Forms\Components\TextInput::make('llm_used')
                            ->disabled(),
                        Forms\Components\TextInput::make('generation_cost')
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\TextInput::make('word_count')
                            ->disabled(),
                        Forms\Components\TextInput::make('generation_time_seconds')
                            ->suffix('seconds')
                            ->disabled(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn(Article $record) => $record->title),
                Tables\Columns\TextColumn::make('site.domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'gray',
                        'generating' => 'warning',
                        'ready' => 'info',
                        'published' => 'success',
                        'failed' => 'danger',
                    }),
                Tables\Columns\TextColumn::make('word_count')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('generation_cost')
                    ->money('EUR', 4)
                    ->sortable(),
                Tables\Columns\TextColumn::make('llm_used')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'generating' => 'Generating',
                        'ready' => 'Ready',
                        'published' => 'Published',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('site_id')
                    ->relationship('site', 'domain')
                    ->label('Site'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('view_content')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn(Article $record) => $record->title)
                    ->modalContent(fn(Article $record) => view('filament.article-preview', ['article' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArticles::route('/'),
            'create' => Pages\CreateArticle::route('/create'),
            'edit' => Pages\EditArticle::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Create article preview blade view**

Create `resources/views/filament/article-preview.blade.php`:

```blade
<div class="prose prose-sm max-w-none dark:prose-invert p-4 max-h-96 overflow-y-auto">
    {!! $article->content !!}
</div>
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(admin): add ArticleResource CRUD with preview"
```

---

## Task 12: Create KeywordResource

**Files:**
- Create: `app/Filament/Resources/KeywordResource.php` + Pages

**Step 1: Generate KeywordResource**

Run:
```bash
php artisan make:filament-resource Keyword --generate
```

**Step 2: Replace KeywordResource content**

Replace `app/Filament/Resources/KeywordResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeywordResource\Pages;
use App\Models\Keyword;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class KeywordResource extends Resource
{
    protected static ?string $model = Keyword::class;
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Keyword Information')
                    ->schema([
                        Forms\Components\TextInput::make('keyword')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('site_id')
                            ->relationship('site', 'domain')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'generating' => 'Generating',
                                'completed' => 'Completed',
                                'failed' => 'Failed',
                            ])
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('SEO Metrics')
                    ->schema([
                        Forms\Components\TextInput::make('volume')
                            ->numeric(),
                        Forms\Components\TextInput::make('difficulty')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                        Forms\Components\TextInput::make('cpc')
                            ->numeric()
                            ->prefix('€'),
                        Forms\Components\TextInput::make('score')
                            ->numeric(),
                    ])->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('site.domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'generating' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('volume')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('difficulty')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('article.title')
                    ->label('Article')
                    ->limit(30)
                    ->url(fn(Keyword $record) => $record->article ? ArticleResource::getUrl('edit', ['record' => $record->article]) : null),
            ])
            ->defaultSort('score', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'generating' => 'Generating',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('site_id')
                    ->relationship('site', 'domain')
                    ->label('Site'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reset')
                    ->label('Reset')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn(Keyword $record) => $record->update(['status' => 'pending']))
                    ->requiresConfirmation()
                    ->visible(fn(Keyword $record) => $record->status === 'failed'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeywords::route('/'),
            'create' => Pages\CreateKeyword::route('/create'),
            'edit' => Pages\EditKeyword::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add KeywordResource CRUD"
```

---

## Task 13: Create IntegrationResource

**Files:**
- Create: `app/Filament/Resources/IntegrationResource.php` + Pages

**Step 1: Generate IntegrationResource**

Run:
```bash
php artisan make:filament-resource Integration --generate
```

**Step 2: Replace IntegrationResource content**

Replace `app/Filament/Resources/IntegrationResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IntegrationResource\Pages;
use App\Models\Integration;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;
    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?int $navigationSort = 6;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Integration Details')
                    ->schema([
                        Forms\Components\Select::make('site_id')
                            ->relationship('site', 'domain')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('type')
                            ->options([
                                'wordpress' => 'WordPress',
                                'ghost' => 'Ghost',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('url')
                            ->url()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Credentials')
                    ->schema([
                        Forms\Components\TextInput::make('username')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('api_key')
                            ->password()
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site.domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'wordpress' => 'info',
                        'ghost' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('url')
                    ->limit(40),
                Tables\Columns\IconColumn::make('is_connected')
                    ->boolean()
                    ->state(fn(Integration $record) => !empty($record->api_key) || !empty($record->password)),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'wordpress' => 'WordPress',
                        'ghost' => 'Ghost',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('test')
                    ->label('Test')
                    ->icon('heroicon-o-bolt')
                    ->action(function (Integration $record) {
                        // TODO: Implement connection test
                        \Filament\Notifications\Notification::make()
                            ->title('Connection test not implemented')
                            ->warning()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListIntegrations::route('/'),
            'create' => Pages\CreateIntegration::route('/create'),
            'edit' => Pages\EditIntegration::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add IntegrationResource CRUD"
```

---

## Task 14: Create ScheduledArticleResource

**Files:**
- Create: `app/Filament/Resources/ScheduledArticleResource.php` + Pages

**Step 1: Generate ScheduledArticleResource**

Run:
```bash
php artisan make:filament-resource ScheduledArticle --generate
```

**Step 2: Replace ScheduledArticleResource content**

Replace `app/Filament/Resources/ScheduledArticleResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScheduledArticleResource\Pages;
use App\Models\ScheduledArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ScheduledArticleResource extends Resource
{
    protected static ?string $model = ScheduledArticle::class;
    protected static ?string $navigationIcon = 'heroicon-o-calendar';
    protected static ?int $navigationSort = 7;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('site_id')
                    ->relationship('site', 'domain')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('keyword_id')
                    ->relationship('keyword', 'keyword')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_for')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('keyword.keyword')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('site.domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('scheduled_for')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'gray',
                        'processing' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('scheduled_for', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'processing' => 'Processing',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                Tables\Filters\SelectFilter::make('site_id')
                    ->relationship('site', 'domain')
                    ->label('Site'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('reschedule')
                    ->label('Reschedule')
                    ->icon('heroicon-o-clock')
                    ->form([
                        Forms\Components\DateTimePicker::make('scheduled_for')
                            ->required(),
                    ])
                    ->action(fn(ScheduledArticle $record, array $data) => $record->update($data))
                    ->visible(fn(ScheduledArticle $record) => $record->status === 'pending'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScheduledArticles::route('/'),
            'create' => Pages\CreateScheduledArticle::route('/create'),
            'edit' => Pages\EditScheduledArticle::route('/{record}/edit'),
        ];
    }
}
```

**Step 3: Commit**

```bash
git add -A && git commit -m "feat(admin): add ScheduledArticleResource CRUD"
```

---

## Task 15: Create CostLogResource

**Files:**
- Create: `app/Filament/Resources/CostLogResource.php` + Pages

**Step 1: Generate CostLogResource**

Run:
```bash
php artisan make:filament-resource CostLog --generate
```

**Step 2: Replace CostLogResource content**

Replace `app/Filament/Resources/CostLogResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CostLogResource\Pages;
use App\Models\CostLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CostLogResource extends Resource
{
    protected static ?string $model = CostLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?int $navigationSort = 8;
    protected static ?string $navigationGroup = 'Monitoring';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('costable_type')
                    ->disabled(),
                Forms\Components\TextInput::make('costable_id')
                    ->disabled(),
                Forms\Components\TextInput::make('type')
                    ->disabled(),
                Forms\Components\TextInput::make('provider')
                    ->disabled(),
                Forms\Components\TextInput::make('model')
                    ->disabled(),
                Forms\Components\TextInput::make('operation')
                    ->disabled(),
                Forms\Components\TextInput::make('cost')
                    ->prefix('€')
                    ->disabled(),
                Forms\Components\TextInput::make('input_tokens')
                    ->disabled(),
                Forms\Components\TextInput::make('output_tokens')
                    ->disabled(),
                Forms\Components\KeyValue::make('metadata')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('costable_type')
                    ->formatStateUsing(fn($state) => class_basename($state))
                    ->label('Type'),
                Tables\Columns\TextColumn::make('operation')
                    ->searchable()
                    ->badge(),
                Tables\Columns\TextColumn::make('provider')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model')
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('cost')
                    ->money('EUR', 6)
                    ->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('EUR', 6)),
                Tables\Columns\TextColumn::make('input_tokens')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('output_tokens')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'llm' => 'LLM',
                        'image' => 'Image',
                        'api' => 'API',
                    ]),
                Tables\Filters\SelectFilter::make('provider')
                    ->options(fn() => CostLog::distinct()->pluck('provider', 'provider')->toArray()),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from'),
                        Forms\Components\DatePicker::make('until'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCostLogs::route('/'),
            'view' => Pages\ViewCostLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
```

**Step 3: Create ViewCostLog page**

Run:
```bash
php artisan make:filament-page ViewCostLog --resource=CostLogResource --type=ViewRecord
```

**Step 4: Commit**

```bash
git add -A && git commit -m "feat(admin): add CostLogResource (read-only)"
```

---

## Task 16: Create AutopilotLogResource

**Files:**
- Create: `app/Filament/Resources/AutopilotLogResource.php` + Pages

**Step 1: Generate AutopilotLogResource**

Run:
```bash
php artisan make:filament-resource AutopilotLog --generate
```

**Step 2: Replace AutopilotLogResource content**

Replace `app/Filament/Resources/AutopilotLogResource.php`:

```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AutopilotLogResource\Pages;
use App\Models\AutopilotLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AutopilotLogResource extends Resource
{
    protected static ?string $model = AutopilotLog::class;
    protected static ?string $navigationIcon = 'heroicon-o-cpu-chip';
    protected static ?int $navigationSort = 9;
    protected static ?string $navigationGroup = 'Monitoring';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('site_id')
                    ->relationship('site', 'domain')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->disabled(),
                Forms\Components\TextInput::make('articles_generated')
                    ->disabled(),
                Forms\Components\TextInput::make('articles_published')
                    ->disabled(),
                Forms\Components\Textarea::make('error_message')
                    ->disabled()
                    ->rows(3),
                Forms\Components\KeyValue::make('metadata')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('site.domain')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'running' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('articles_generated')
                    ->numeric(),
                Tables\Columns\TextColumn::make('articles_published')
                    ->numeric(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ran At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'running' => 'Running',
                    ]),
                Tables\Filters\SelectFilter::make('site_id')
                    ->relationship('site', 'domain')
                    ->label('Site'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('view_details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn(AutopilotLog $record) => "Autopilot Run - {$record->site->domain}")
                    ->modalContent(fn(AutopilotLog $record) => view('filament.autopilot-log-details', ['log' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAutopilotLogs::route('/'),
            'view' => Pages\ViewAutopilotLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
```

**Step 3: Create ViewAutopilotLog page**

Run:
```bash
php artisan make:filament-page ViewAutopilotLog --resource=AutopilotLogResource --type=ViewRecord
```

**Step 4: Create autopilot log details blade view**

Create `resources/views/filament/autopilot-log-details.blade.php`:

```blade
<div class="p-4 space-y-4">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Status:</span>
            <span class="ml-2 text-sm">{{ $log->status }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Ran At:</span>
            <span class="ml-2 text-sm">{{ $log->created_at->format('Y-m-d H:i:s') }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Articles Generated:</span>
            <span class="ml-2 text-sm">{{ $log->articles_generated ?? 0 }}</span>
        </div>
        <div>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Articles Published:</span>
            <span class="ml-2 text-sm">{{ $log->articles_published ?? 0 }}</span>
        </div>
    </div>

    @if($log->error_message)
        <div class="mt-4">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Error:</span>
            <pre class="mt-1 p-2 bg-red-50 dark:bg-red-900/20 rounded text-red-600 dark:text-red-400 text-xs overflow-x-auto">{{ $log->error_message }}</pre>
        </div>
    @endif

    @if($log->metadata)
        <div class="mt-4">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Metadata:</span>
            <pre class="mt-1 p-2 bg-gray-50 dark:bg-gray-800 rounded text-xs overflow-x-auto">{{ json_encode($log->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    @endif
</div>
```

**Step 5: Commit**

```bash
git add -A && git commit -m "feat(admin): add AutopilotLogResource (read-only)"
```

---

## Task 17: Final Testing & Cleanup

**Step 1: Run all migrations fresh**

Run:
```bash
php artisan migrate:fresh --seed
```

**Step 2: Start dev server and test**

Run:
```bash
php artisan serve
```

Visit `http://localhost:8000/ctrl-56389305` and verify:
- Login works with admin user
- Dashboard shows all widgets
- All 9 resources are accessible in sidebar
- CRUD operations work for each resource

**Step 3: Run linting**

Run:
```bash
./vendor/bin/pint
```

**Step 4: Run tests**

Run:
```bash
php artisan test
```

**Step 5: Final commit**

```bash
git add -A && git commit -m "chore(admin): final cleanup and formatting"
```

---

## Summary

This plan implements:
- Filament v4 admin panel at `/ctrl-56389305`
- 9 CRUD resources (Teams, Plans, Sites, Articles, Keywords, Integrations, ScheduledArticles, CostLogs, AutopilotLogs)
- 7 dashboard widgets (StatsOverview, ArticlesChart, ClientDistribution, CostBreakdown, TopClients, RecentFailures)
- Full cost tracking via `cost_logs` polymorphic table
- Auth via `is_admin` boolean on User model
