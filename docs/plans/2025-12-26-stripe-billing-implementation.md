# Stripe Billing Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement complete Stripe billing with trials, plans management, and anti-abuse protections.

**Architecture:** Dynamic plans table with PlanSeeder, Team-based billing via Laravel Cashier, middleware for subscription checks, manual article allocation per site.

**Tech Stack:** Laravel Cashier, Stripe API, Inertia/React, SQLite (tests) / MySQL (prod)

**Design Document:** [docs/plans/2025-12-26-stripe-billing-design.md](2025-12-26-stripe-billing-design.md)

---

## Phase 1: Database & Models

### Task 1.1: Create Plans Migration

**Files:**
- Create: `database/migrations/2025_12_26_000001_create_plans_table.php`

**Step 1: Create migration file**

```bash
php artisan make:migration create_plans_table
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->integer('price');
            $table->integer('articles_per_month');
            $table->integer('sites_limit');
            $table->string('stripe_price_id')->nullable();
            $table->json('features');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*create_plans_table*
git commit -m "feat(billing): create plans table migration"
```

---

### Task 1.2: Create Plan Model

**Files:**
- Create: `app/Models/Plan.php`
- Test: `tests/Unit/Models/PlanTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_has_fillable_attributes(): void
    {
        $plan = Plan::create([
            'slug' => 'test',
            'name' => 'Test Plan',
            'price' => 99,
            'articles_per_month' => 30,
            'sites_limit' => 3,
            'features' => ['Feature 1', 'Feature 2'],
        ]);

        $this->assertEquals('test', $plan->slug);
        $this->assertEquals(99, $plan->price);
        $this->assertIsArray($plan->features);
    }

    public function test_is_unlimited_sites_returns_true_for_negative_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => -1]);
        $this->assertTrue($plan->isUnlimitedSites());
    }

    public function test_is_unlimited_sites_returns_false_for_positive_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $this->assertFalse($plan->isUnlimitedSites());
    }

    public function test_active_scope_returns_only_active_plans(): void
    {
        Plan::factory()->create(['is_active' => true, 'slug' => 'active']);
        Plan::factory()->create(['is_active' => false, 'slug' => 'inactive']);

        $activePlans = Plan::active()->get();

        $this->assertCount(1, $activePlans);
        $this->assertEquals('active', $activePlans->first()->slug);
    }
}
```

**Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Models/PlanTest.php
```

Expected: FAIL - Plan model not found

**Step 3: Create Plan model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'price',
        'articles_per_month',
        'sites_limit',
        'stripe_price_id',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price' => 'integer',
        'articles_per_month' => 'integer',
        'sites_limit' => 'integer',
        'sort_order' => 'integer',
    ];

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function isUnlimitedSites(): bool
    {
        return $this->sites_limit === -1;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
```

**Step 4: Create Plan factory**

```php
<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'slug' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'price' => $this->faker->randomElement([39, 99, 249]),
            'articles_per_month' => $this->faker->randomElement([8, 30, 100]),
            'sites_limit' => $this->faker->randomElement([1, 3, -1]),
            'stripe_price_id' => null,
            'features' => ['Feature 1', 'Feature 2'],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function starter(): static
    {
        return $this->state([
            'slug' => 'starter',
            'name' => 'Starter',
            'price' => 39,
            'articles_per_month' => 8,
            'sites_limit' => 1,
        ]);
    }

    public function pro(): static
    {
        return $this->state([
            'slug' => 'pro',
            'name' => 'Pro',
            'price' => 99,
            'articles_per_month' => 30,
            'sites_limit' => 3,
        ]);
    }

    public function agency(): static
    {
        return $this->state([
            'slug' => 'agency',
            'name' => 'Agency',
            'price' => 249,
            'articles_per_month' => 100,
            'sites_limit' => -1,
        ]);
    }
}
```

**Step 5: Run tests**

```bash
php artisan test tests/Unit/Models/PlanTest.php
```

Expected: PASS

**Step 6: Commit**

```bash
git add app/Models/Plan.php database/factories/PlanFactory.php tests/Unit/Models/PlanTest.php
git commit -m "feat(billing): add Plan model with factory and tests"
```

---

### Task 1.3: Create PlanSeeder

**Files:**
- Create: `database/seeders/PlanSeeder.php`

**Step 1: Create seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'starter',
                'name' => 'Starter',
                'price' => 39,
                'articles_per_month' => 8,
                'sites_limit' => 1,
                'features' => [
                    '8 articles/mois',
                    '1 site',
                    'Support email',
                    'Analytics de base',
                ],
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'price' => 99,
                'articles_per_month' => 30,
                'sites_limit' => 3,
                'features' => [
                    '30 articles/mois',
                    '3 sites',
                    'Support prioritaire',
                    'Analytics avancés',
                    'Voix de marque personnalisée',
                ],
                'sort_order' => 2,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'price' => 249,
                'articles_per_month' => 100,
                'sites_limit' => -1,
                'features' => [
                    '100 articles/mois',
                    'Sites illimités',
                    'Support dédié',
                    'API access',
                    'Intégrations personnalisées',
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::updateOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
```

**Step 2: Register in DatabaseSeeder**

Add to `database/seeders/DatabaseSeeder.php`:

```php
$this->call([
    PlanSeeder::class,
]);
```

**Step 3: Run seeder**

```bash
php artisan db:seed --class=PlanSeeder
```

**Step 4: Commit**

```bash
git add database/seeders/PlanSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat(billing): add PlanSeeder with starter, pro, agency plans"
```

---

### Task 1.4: Modify Teams Table

**Files:**
- Create: `database/migrations/2025_12_26_000002_add_billing_columns_to_teams_table.php`
- Modify: `app/Models/Team.php`
- Test: `tests/Unit/Models/TeamTest.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_billing_columns_to_teams_table
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('owner_id')->constrained();
            $table->boolean('is_trial')->default(true)->after('plan_id');
            $table->timestamp('trial_ends_at')->nullable()->after('is_trial');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['is_trial', 'trial_ends_at']);
        });
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Write tests for Team model updates**

Add to existing TeamTest or create new file `tests/Unit/Models/TeamBillingTest.php`:

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_belongs_to_plan(): void
    {
        $plan = Plan::factory()->pro()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertEquals($plan->id, $team->plan->id);
    }

    public function test_is_trial_expired_returns_true_when_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => true,
            'trial_ends_at' => now()->subDay(),
        ]);

        $this->assertTrue($team->isTrialExpired());
    }

    public function test_is_trial_expired_returns_false_when_active(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => true,
            'trial_ends_at' => now()->addDays(3),
        ]);

        $this->assertFalse($team->isTrialExpired());
    }

    public function test_is_trial_expired_returns_false_when_not_trial(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'is_trial' => false,
            'trial_ends_at' => null,
        ]);

        $this->assertFalse($team->isTrialExpired());
    }

    public function test_can_create_site_returns_true_when_under_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($team->canCreateSite());
    }

    public function test_can_create_site_returns_true_for_unlimited(): void
    {
        $plan = Plan::factory()->agency()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
        ]);

        $this->assertTrue($team->canCreateSite());
    }
}
```

**Step 5: Run tests to see them fail**

```bash
php artisan test tests/Unit/Models/TeamBillingTest.php
```

**Step 6: Update Team model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Team extends Model
{
    use Billable, HasFactory;

    protected $fillable = [
        'name',
        'owner_id',
        'plan_id',
        'is_trial',
        'trial_ends_at',
    ];

    protected $casts = [
        'is_trial' => 'boolean',
        'trial_ends_at' => 'datetime',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function isTrialExpired(): bool
    {
        if (!$this->is_trial) {
            return false;
        }

        return $this->trial_ends_at && $this->trial_ends_at->isPast();
    }

    public function canCreateSite(): bool
    {
        if (!$this->plan) {
            return true; // Trial period allows 1 site
        }

        if ($this->plan->isUnlimitedSites()) {
            return true;
        }

        return $this->sites()->count() < $this->plan->sites_limit;
    }

    public function canGenerateArticle(): bool
    {
        if ($this->isTrialExpired() && !$this->subscribed()) {
            return false;
        }

        $limit = $this->plan?->articles_per_month ?? 2; // Trial = 2 articles
        return $this->articlesUsedThisMonth() < $limit;
    }

    public function articlesUsedThisMonth(): int
    {
        return Article::whereIn('site_id', $this->sites->pluck('id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    }

    public function getArticlesLimitAttribute(): int
    {
        return $this->plan?->articles_per_month ?? 2;
    }
}
```

**Step 7: Update TeamFactory**

Add to `database/factories/TeamFactory.php`:

```php
protected $model = Team::class;

public function definition(): array
{
    return [
        'name' => $this->faker->company(),
        'owner_id' => User::factory(),
        'plan_id' => null,
        'is_trial' => true,
        'trial_ends_at' => now()->addDays(7),
    ];
}

public function withPlan(Plan $plan): static
{
    return $this->state([
        'plan_id' => $plan->id,
        'is_trial' => false,
        'trial_ends_at' => null,
    ]);
}

public function trialExpired(): static
{
    return $this->state([
        'is_trial' => true,
        'trial_ends_at' => now()->subDay(),
    ]);
}
```

**Step 8: Run tests**

```bash
php artisan test tests/Unit/Models/TeamBillingTest.php
```

Expected: PASS

**Step 9: Commit**

```bash
git add database/migrations/*add_billing_columns_to_teams* app/Models/Team.php database/factories/TeamFactory.php tests/Unit/Models/TeamBillingTest.php
git commit -m "feat(billing): add billing columns to teams table"
```

---

### Task 1.5: Add articles_allocated to site_settings

**Files:**
- Create: `database/migrations/2025_12_26_000003_add_articles_allocated_to_site_settings.php`
- Modify: `app/Models/SiteSetting.php`

**Step 1: Create migration**

```bash
php artisan make:migration add_articles_allocated_to_site_settings
```

**Step 2: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->integer('articles_allocated')->default(0)->after('auto_publish');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('articles_allocated');
        });
    }
};
```

**Step 3: Run migration**

```bash
php artisan migrate
```

**Step 4: Update SiteSetting model fillable**

Add `'articles_allocated'` to the `$fillable` array in `app/Models/SiteSetting.php`.

**Step 5: Commit**

```bash
git add database/migrations/*add_articles_allocated* app/Models/SiteSetting.php
git commit -m "feat(billing): add articles_allocated to site_settings"
```

---

## Phase 2: Anti-Abuse Protection

### Task 2.1: Block Existing Domains

**Files:**
- Modify: `app/Http/Controllers/Web/OnboardingController.php`
- Test: `tests/Feature/Onboarding/Step1Test.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Onboarding;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step1DomainCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_registration_if_domain_exists(): void
    {
        $existingTeam = Team::factory()->create();
        Site::factory()->create([
            'team_id' => $existingTeam->id,
            'domain' => 'example.com',
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'url' => 'https://example.com/blog',
            'name' => 'My Site',
        ]);

        $response->assertSessionHasErrors('url');
    }

    public function test_allows_registration_for_new_domain(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('onboarding.step1'), [
            'url' => 'https://newdomain.com',
            'name' => 'My Site',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
```

**Step 2: Run test to see it fail**

```bash
php artisan test tests/Feature/Onboarding/Step1DomainCheckTest.php
```

**Step 3: Update OnboardingController**

In `app/Http/Controllers/Web/OnboardingController.php`, update the `step1` method:

```php
public function step1(Request $request)
{
    $validated = $request->validate([
        'url' => ['required', 'url'],
        'name' => ['required', 'string', 'max:255'],
    ]);

    // Extract domain from URL
    $domain = parse_url($validated['url'], PHP_URL_HOST);
    $domain = preg_replace('/^www\./', '', $domain);

    // Check if domain already exists
    $existingSite = Site::where('domain', $domain)->first();
    if ($existingSite) {
        return back()->withErrors([
            'url' => 'Ce site est déjà enregistré. Contactez support@seo-autopilot.com si vous êtes le propriétaire.',
        ])->withInput();
    }

    // Continue with site creation...
    $team = $request->user()->currentTeam;

    $site = Site::create([
        'team_id' => $team->id,
        'name' => $validated['name'],
        'domain' => $domain,
        'url' => $validated['url'],
    ]);

    return redirect()->route('onboarding.step2', $site);
}
```

**Step 4: Run tests**

```bash
php artisan test tests/Feature/Onboarding/Step1DomainCheckTest.php
```

Expected: PASS

**Step 5: Commit**

```bash
git add app/Http/Controllers/Web/OnboardingController.php tests/Feature/Onboarding/Step1DomainCheckTest.php
git commit -m "feat(billing): block registration for existing domains"
```

---

### Task 2.2: Block Site Creation When Quota Reached

**Files:**
- Modify: `app/Http/Controllers/Web/SiteController.php`
- Test: `tests/Feature/Site/CreateSiteTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Site;

use App\Models\Plan;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateSiteQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_site_creation_when_quota_reached(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 1]);
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);
        $user->update(['current_team_id' => $team->id]);

        // Already has 1 site
        Site::factory()->create(['team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'url' => 'https://newsite.com',
            'name' => 'New Site',
        ]);

        $response->assertStatus(403);
    }

    public function test_allows_site_creation_when_under_quota(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'url' => 'https://newsite.com',
            'name' => 'New Site',
        ]);

        $response->assertSessionHasNoErrors();
    }
}
```

**Step 2: Update SiteController**

Add quota check at the beginning of the `store` method:

```php
public function store(Request $request)
{
    $team = $request->user()->currentTeam;

    if (!$team->canCreateSite()) {
        abort(403, 'Vous avez atteint la limite de sites pour votre plan. Passez à un plan supérieur pour ajouter plus de sites.');
    }

    // ... rest of the method
}
```

**Step 3: Run tests**

```bash
php artisan test tests/Feature/Site/CreateSiteQuotaTest.php
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Web/SiteController.php tests/Feature/Site/CreateSiteQuotaTest.php
git commit -m "feat(billing): block site creation when quota reached"
```

---

## Phase 3: Subscription Middleware

### Task 3.1: Create CheckSubscription Middleware

**Files:**
- Create: `app/Http/Middleware/CheckSubscription.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/Middleware/CheckSubscriptionTest.php`

**Step 1: Write failing test**

```php
<?php

namespace Tests\Feature\Middleware;

use App\Models\Plan;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_allows_get_requests_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
    }

    public function test_blocks_post_requests_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'url' => 'https://test.com',
            'name' => 'Test',
        ]);

        $response->assertRedirect(route('settings.billing'));
    }

    public function test_allows_billing_routes_when_trial_expired(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->trialExpired()->create(['owner_id' => $user->id]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->get(route('settings.billing'));

        $response->assertStatus(200);
    }

    public function test_allows_all_requests_with_active_subscription(): void
    {
        $plan = Plan::factory()->pro()->create();
        $user = User::factory()->create();
        $team = Team::factory()->create([
            'owner_id' => $user->id,
            'plan_id' => $plan->id,
            'is_trial' => false,
        ]);
        $user->update(['current_team_id' => $team->id]);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'url' => 'https://test.com',
            'name' => 'Test',
        ]);

        // Should not redirect to billing
        $response->assertSessionHasNoErrors();
    }
}
```

**Step 2: Run test to see it fail**

```bash
php artisan test tests/Feature/Middleware/CheckSubscriptionTest.php
```

**Step 3: Create middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    protected array $exemptRoutes = [
        'settings.billing',
        'billing.*',
        'logout',
        'stripe.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->currentTeam) {
            return $next($request);
        }

        $team = $user->currentTeam;

        // Allow if not expired or has active subscription
        if (!$team->isTrialExpired() || $team->subscribed()) {
            return $next($request);
        }

        // Trial expired, no subscription - freeze account

        // Allow GET requests (read-only access)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Allow billing routes
        foreach ($this->exemptRoutes as $route) {
            if ($request->routeIs($route)) {
                return $next($request);
            }
        }

        // Block write actions
        return redirect()->route('settings.billing')
            ->with('warning', 'Votre période d\'essai est terminée. Choisissez un plan pour continuer.');
    }
}
```

**Step 4: Register middleware in bootstrap/app.php**

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
        \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        \App\Http\Middleware\CheckSubscription::class,
    ]);
})
```

**Step 5: Run tests**

```bash
php artisan test tests/Feature/Middleware/CheckSubscriptionTest.php
```

**Step 6: Commit**

```bash
git add app/Http/Middleware/CheckSubscription.php bootstrap/app.php tests/Feature/Middleware/CheckSubscriptionTest.php
git commit -m "feat(billing): add CheckSubscription middleware for account freeze"
```

---

## Phase 4: Stripe Integration

### Task 4.1: Create BillingController

**Files:**
- Create: `app/Http/Controllers/Web/BillingController.php`
- Modify: `routes/web.php`

**Step 1: Create controller**

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;

        return Inertia::render('Settings/Billing', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'is_trial' => $team->is_trial,
                'trial_ends_at' => $team->trial_ends_at?->toISOString(),
                'plan' => $team->plan,
                'subscribed' => $team->subscribed(),
            ],
            'plans' => Plan::active()->orderBy('sort_order')->get(),
            'intent' => $team->createSetupIntent(),
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $plan = Plan::findOrFail($request->plan_id);
        $team = $request->user()->currentTeam;

        if (!$plan->stripe_price_id) {
            return back()->withErrors(['plan_id' => 'Ce plan n\'est pas encore disponible.']);
        }

        $checkout = $team->newSubscription('default', $plan->stripe_price_id)
            ->checkout([
                'success_url' => route('billing.success') . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('billing.cancel'),
            ]);

        return Inertia::location($checkout->url);
    }

    public function success(Request $request)
    {
        $team = $request->user()->currentTeam;

        // Update team to mark trial as complete
        if ($team->is_trial) {
            $team->update([
                'is_trial' => false,
                'trial_ends_at' => null,
            ]);
        }

        return redirect()->route('settings.billing')
            ->with('success', 'Votre abonnement a été activé avec succès !');
    }

    public function cancel()
    {
        return redirect()->route('settings.billing')
            ->with('info', 'Le paiement a été annulé.');
    }

    public function portal(Request $request)
    {
        return Inertia::location(
            $request->user()->currentTeam->billingPortalUrl(route('settings.billing'))
        );
    }
}
```

**Step 2: Add routes**

In `routes/web.php`:

```php
Route::middleware(['auth', 'verified'])->group(function () {
    // ... existing routes

    // Billing
    Route::get('/billing', [BillingController::class, 'index'])->name('settings.billing');
    Route::post('/billing/checkout', [BillingController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/success', [BillingController::class, 'success'])->name('billing.success');
    Route::get('/billing/cancel', [BillingController::class, 'cancel'])->name('billing.cancel');
    Route::post('/billing/portal', [BillingController::class, 'portal'])->name('billing.portal');
});
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Web/BillingController.php routes/web.php
git commit -m "feat(billing): add BillingController with checkout and portal"
```

---

### Task 4.2: Create StripeWebhookController

**Files:**
- Create: `app/Http/Controllers/Web/StripeWebhookController.php`
- Modify: `routes/web.php`

**Step 1: Create webhook controller**

```php
<?php

namespace App\Http\Controllers\Web;

use App\Models\Plan;
use App\Models\Team;
use Illuminate\Http\Request;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierController;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends CashierController
{
    public function handleCustomerSubscriptionCreated(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];
        $stripePriceId = $data['items']['data'][0]['price']['id'] ?? null;

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team && $stripePriceId) {
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();

            if ($plan) {
                $team->update([
                    'plan_id' => $plan->id,
                    'is_trial' => false,
                    'trial_ends_at' => null,
                ]);
            }
        }

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];
        $stripePriceId = $data['items']['data'][0]['price']['id'] ?? null;

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team && $stripePriceId) {
            $plan = Plan::where('stripe_price_id', $stripePriceId)->first();

            if ($plan) {
                $team->update(['plan_id' => $plan->id]);
            }
        }

        return $this->successMethod();
    }

    public function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        $data = $payload['data']['object'];
        $stripeCustomerId = $data['customer'];

        $team = Team::where('stripe_id', $stripeCustomerId)->first();

        if ($team) {
            $team->update([
                'plan_id' => null,
                'is_trial' => true,
                'trial_ends_at' => now(), // Immediately expired
            ]);
        }

        return $this->successMethod();
    }
}
```

**Step 2: Add webhook route**

In `routes/web.php`, outside auth middleware:

```php
use App\Http\Controllers\Web\StripeWebhookController;

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');
```

**Step 3: Exclude from CSRF**

In `bootstrap/app.php` or create `app/Http/Middleware/VerifyCsrfToken.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
    ]);
})
```

**Step 4: Commit**

```bash
git add app/Http/Controllers/Web/StripeWebhookController.php routes/web.php bootstrap/app.php
git commit -m "feat(billing): add Stripe webhook controller"
```

---

## Phase 5: Frontend

### Task 5.1: Create Billing Page Component

**Files:**
- Create: `resources/js/Pages/Settings/Billing.tsx`

**Step 1: Create component**

```tsx
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CreditCard, Check, Zap, Building2, Rocket } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import clsx from 'clsx';

interface Plan {
    id: number;
    slug: string;
    name: string;
    price: number;
    articles_per_month: number;
    sites_limit: number;
    features: string[];
}

interface Props {
    team: {
        id: number;
        name: string;
        is_trial: boolean;
        trial_ends_at: string | null;
        plan: Plan | null;
        subscribed: boolean;
    };
    plans: Plan[];
}

const planIcons: Record<string, typeof Zap> = {
    starter: Zap,
    pro: Rocket,
    agency: Building2,
};

export default function Billing({ team, plans }: Props) {
    const [loading, setLoading] = useState<number | null>(null);

    const handleCheckout = (planId: number) => {
        setLoading(planId);
        router.post(route('billing.checkout'), { plan_id: planId });
    };

    const handlePortal = () => {
        router.post(route('billing.portal'));
    };

    const trialDaysLeft = team.trial_ends_at
        ? Math.max(0, Math.ceil((new Date(team.trial_ends_at).getTime() - Date.now()) / (1000 * 60 * 60 * 24)))
        : 0;

    return (
        <AuthenticatedLayout>
            <Head title="Facturation" />

            <div className="mx-auto max-w-5xl px-4 py-8">
                <h1 className="font-display text-3xl font-bold text-surface-900 dark:text-white">
                    Facturation
                </h1>

                {/* Current Status */}
                <div className="mt-8 rounded-2xl border border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 p-6">
                    <h2 className="text-lg font-semibold text-surface-900 dark:text-white">
                        Votre abonnement
                    </h2>

                    {team.is_trial ? (
                        <div className="mt-4">
                            <div className="flex items-center gap-3">
                                <span className="rounded-full bg-amber-100 dark:bg-amber-500/20 px-3 py-1 text-sm font-medium text-amber-700 dark:text-amber-400">
                                    Période d'essai
                                </span>
                                {trialDaysLeft > 0 ? (
                                    <span className="text-sm text-surface-500">
                                        {trialDaysLeft} jour{trialDaysLeft > 1 ? 's' : ''} restant{trialDaysLeft > 1 ? 's' : ''}
                                    </span>
                                ) : (
                                    <span className="text-sm text-red-500 font-medium">
                                        Expirée
                                    </span>
                                )}
                            </div>
                            <p className="mt-2 text-surface-600 dark:text-surface-400">
                                2 articles gratuits pour découvrir la plateforme
                            </p>
                        </div>
                    ) : team.plan ? (
                        <div className="mt-4">
                            <div className="flex items-center justify-between">
                                <div className="flex items-center gap-3">
                                    <span className="rounded-full bg-primary-100 dark:bg-primary-500/20 px-3 py-1 text-sm font-medium text-primary-700 dark:text-primary-400">
                                        Plan {team.plan.name}
                                    </span>
                                    <span className="text-2xl font-bold text-surface-900 dark:text-white">
                                        ${team.plan.price}/mois
                                    </span>
                                </div>
                                <button
                                    onClick={handlePortal}
                                    className="rounded-lg border border-surface-300 dark:border-surface-600 px-4 py-2 text-sm font-medium hover:bg-surface-50 dark:hover:bg-surface-700 transition-colors"
                                >
                                    Gérer l'abonnement
                                </button>
                            </div>
                            <p className="mt-2 text-surface-600 dark:text-surface-400">
                                {team.plan.articles_per_month} articles/mois •{' '}
                                {team.plan.sites_limit === -1 ? 'Sites illimités' : `${team.plan.sites_limit} site${team.plan.sites_limit > 1 ? 's' : ''}`}
                            </p>
                        </div>
                    ) : null}
                </div>

                {/* Plans */}
                <div className="mt-8">
                    <h2 className="text-lg font-semibold text-surface-900 dark:text-white">
                        {team.plan ? 'Changer de plan' : 'Choisir un plan'}
                    </h2>

                    <div className="mt-6 grid gap-6 md:grid-cols-3">
                        {plans.map((plan) => {
                            const Icon = planIcons[plan.slug] || Zap;
                            const isCurrent = team.plan?.id === plan.id;
                            const isPopular = plan.slug === 'pro';

                            return (
                                <div
                                    key={plan.id}
                                    className={clsx(
                                        'relative rounded-2xl border p-6 transition-all',
                                        isCurrent
                                            ? 'border-primary-500 bg-primary-50/50 dark:bg-primary-500/10 ring-2 ring-primary-500/20'
                                            : 'border-surface-200 dark:border-surface-700 bg-white dark:bg-surface-800 hover:border-surface-300 dark:hover:border-surface-600'
                                    )}
                                >
                                    {isPopular && !isCurrent && (
                                        <div className="absolute -top-3 left-1/2 -translate-x-1/2">
                                            <span className="rounded-full bg-primary-500 px-3 py-1 text-xs font-semibold text-white">
                                                Populaire
                                            </span>
                                        </div>
                                    )}

                                    <div className="flex items-center gap-3">
                                        <div className={clsx(
                                            'flex h-10 w-10 items-center justify-center rounded-xl',
                                            isCurrent ? 'bg-primary-100 dark:bg-primary-500/20' : 'bg-surface-100 dark:bg-surface-700'
                                        )}>
                                            <Icon className={clsx(
                                                'h-5 w-5',
                                                isCurrent ? 'text-primary-600' : 'text-surface-500'
                                            )} />
                                        </div>
                                        <h3 className="text-lg font-semibold text-surface-900 dark:text-white">
                                            {plan.name}
                                        </h3>
                                    </div>

                                    <div className="mt-4">
                                        <span className="text-4xl font-bold text-surface-900 dark:text-white">
                                            ${plan.price}
                                        </span>
                                        <span className="text-surface-500">/mois</span>
                                    </div>

                                    <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
                                        {plan.articles_per_month} articles/mois •{' '}
                                        {plan.sites_limit === -1 ? 'Sites illimités' : `${plan.sites_limit} site${plan.sites_limit > 1 ? 's' : ''}`}
                                    </p>

                                    <ul className="mt-6 space-y-3">
                                        {plan.features.map((feature, index) => (
                                            <li key={index} className="flex items-start gap-2 text-sm">
                                                <Check className="h-5 w-5 flex-shrink-0 text-primary-500" />
                                                <span className="text-surface-600 dark:text-surface-400">{feature}</span>
                                            </li>
                                        ))}
                                    </ul>

                                    <button
                                        onClick={() => handleCheckout(plan.id)}
                                        disabled={isCurrent || loading !== null}
                                        className={clsx(
                                            'mt-6 w-full rounded-xl py-3 text-sm font-semibold transition-all',
                                            isCurrent
                                                ? 'bg-surface-100 dark:bg-surface-700 text-surface-400 cursor-not-allowed'
                                                : 'bg-primary-500 text-white hover:bg-primary-600 shadow-green hover:shadow-green-lg'
                                        )}
                                    >
                                        {loading === plan.id ? (
                                            <span className="flex items-center justify-center gap-2">
                                                <div className="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white" />
                                                Redirection...
                                            </span>
                                        ) : isCurrent ? (
                                            'Plan actuel'
                                        ) : team.plan ? (
                                            plan.price > team.plan.price ? 'Upgrader' : 'Downgrader'
                                        ) : (
                                            'Choisir ce plan'
                                        )}
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* FAQ or Help */}
                <div className="mt-12 rounded-2xl bg-surface-50 dark:bg-surface-800/50 p-6">
                    <h3 className="font-semibold text-surface-900 dark:text-white">
                        Questions fréquentes
                    </h3>
                    <div className="mt-4 space-y-4 text-sm text-surface-600 dark:text-surface-400">
                        <p>
                            <strong>Puis-je changer de plan ?</strong><br />
                            Oui, vous pouvez upgrader à tout moment. Le changement prend effet immédiatement avec un prorata.
                            Les downgrades prennent effet à la fin de votre période de facturation.
                        </p>
                        <p>
                            <strong>Que se passe-t-il si je dépasse mon quota ?</strong><br />
                            Vous ne pourrez plus générer d'articles jusqu'au renouvellement de votre quota le mois suivant.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
```

**Step 2: Run TypeScript check**

```bash
npm run typecheck
```

**Step 3: Commit**

```bash
git add resources/js/Pages/Settings/Billing.tsx
git commit -m "feat(billing): add Billing page component"
```

---

### Task 5.2: Update Settings Index with Trial Banner

**Files:**
- Modify: `resources/js/Pages/Settings/Index.tsx`

**Step 1: Add trial warning banner**

Add this component at the top of the Settings page to show trial status:

```tsx
{team.is_trial && (
    <div className="mb-6 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 p-4">
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-500/20">
                    <Clock className="h-5 w-5 text-amber-600" />
                </div>
                <div>
                    <p className="font-medium text-amber-800 dark:text-amber-400">
                        Période d'essai
                    </p>
                    <p className="text-sm text-amber-600 dark:text-amber-500">
                        {trialDaysLeft > 0
                            ? `${trialDaysLeft} jour${trialDaysLeft > 1 ? 's' : ''} restant${trialDaysLeft > 1 ? 's' : ''}`
                            : 'Expirée - choisissez un plan pour continuer'}
                    </p>
                </div>
            </div>
            <Link
                href={route('settings.billing')}
                className="rounded-lg bg-amber-500 px-4 py-2 text-sm font-medium text-white hover:bg-amber-600 transition-colors"
            >
                Voir les plans
            </Link>
        </div>
    </div>
)}
```

**Step 2: Commit**

```bash
git add resources/js/Pages/Settings/Index.tsx
git commit -m "feat(billing): add trial banner to settings page"
```

---

## Phase 6: Trial Setup

### Task 6.1: Set Trial on Team Creation

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php` or wherever teams are created

**Step 1: Update team creation logic**

When a new team is created (during registration), set trial:

```php
$team = Team::create([
    'name' => $user->name . "'s Team",
    'owner_id' => $user->id,
    'is_trial' => true,
    'trial_ends_at' => now()->addDays(7),
]);
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Auth/RegisteredUserController.php
git commit -m "feat(billing): set 7-day trial on team creation"
```

---

## Final: Run All Tests

```bash
php artisan test
```

Expected: All tests pass (except pre-existing failures)

---

## Stripe Dashboard Setup (Manual)

After implementation, configure in Stripe Dashboard:

1. **Create Products:**
   - Starter ($39/month)
   - Pro ($99/month)
   - Agency ($249/month)

2. **Copy Price IDs** and update plans table:
   ```sql
   UPDATE plans SET stripe_price_id = 'price_xxx' WHERE slug = 'starter';
   UPDATE plans SET stripe_price_id = 'price_yyy' WHERE slug = 'pro';
   UPDATE plans SET stripe_price_id = 'price_zzz' WHERE slug = 'agency';
   ```

3. **Configure Webhook:**
   - URL: `https://yourdomain.com/stripe/webhook`
   - Events: `customer.subscription.created`, `customer.subscription.updated`, `customer.subscription.deleted`, `invoice.payment_failed`

4. **Add keys to .env:**
   ```
   STRIPE_KEY=pk_live_xxx
   STRIPE_SECRET=sk_live_xxx
   STRIPE_WEBHOOK_SECRET=whsec_xxx
   ```
