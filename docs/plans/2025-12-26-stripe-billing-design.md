# Stripe Billing & Plans - Design Document

**Date**: 2025-12-26
**Status**: Approved

## Overview

Implement a complete billing system with Stripe integration, dynamic plans management, and anti-abuse protections.

### Key Features

- **Trial period**: 7 days, 2 articles max, 1 site
- **3 paid plans**: Starter ($39), Pro ($99), Agency ($249)
- **Anti-abuse**: Block registration if domain already exists
- **Manual allocation**: User distributes articles across their sites
- **Account freeze**: Read-only access after trial expires (no payment)

### User Flow

```
Signup → Trial 7d (2 articles, 1 site)
                ↓
        Trial expires without payment
                ↓
        Account frozen (read-only)
                ↓
        Upgrade → Access restored
```

### Behavior Summary

| Action | Behavior |
|--------|----------|
| Upgrade | Immediate + prorated |
| Downgrade | End of billing period |
| Existing site | Block at onboarding Step1 |
| Sites quota reached | Block new site creation |
| Trial expired | Freeze account, read-only |
| Multiple articles/day | Allowed, displayed clearly to user |

---

## Plans

| Plan | Price | Articles/month | Sites | Target |
|------|-------|----------------|-------|--------|
| **Trial** | Free | 2 (total) | 1 | Product testing |
| **Starter** | $39/mo | 8 | 1 | Solopreneurs, personal blogs |
| **Pro** | $99/mo | 30 | 3 | SMBs, e-commerce |
| **Agency** | $249/mo | 100 | Unlimited | Agencies, multi-site |

### Quota Management

- Quota is **monthly**, resets on the 1st of each month
- **Manual allocation**: User decides how many articles per site
- Multiple articles per day allowed if user chooses few publishing days
- Clear display: "20 articles/month on 3 days = ~2 articles per publishing day"

---

## Database Schema

### New table: `plans`

```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();           // 'starter', 'pro', 'agency'
    $table->string('name');                      // 'Starter', 'Pro', 'Agency'
    $table->integer('price');                    // 39, 99, 249 (dollars)
    $table->integer('articles_per_month');       // 8, 30, 100
    $table->integer('sites_limit');              // 1, 3, -1 (unlimited)
    $table->string('stripe_price_id')->nullable();
    $table->json('features');                    // ["Feature 1", "Feature 2"]
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### Modify table: `teams`

```php
// Add
$table->foreignId('plan_id')->nullable()->constrained();
$table->timestamp('trial_ends_at')->nullable();
$table->boolean('is_trial')->default(true);

// Remove (deprecated by plan_id)
// $table->string('plan');
// $table->integer('articles_limit');
```

### Modify table: `site_settings`

```php
// Add
$table->integer('articles_allocated')->default(0);
```

---

## Business Logic

### Anti-abuse: Existing Site Check

```php
// OnboardingController@step1 or SiteController@store
$domain = parse_url($request->url, PHP_URL_HOST);
$exists = Site::where('domain', $domain)->exists();

if ($exists) {
    return back()->withErrors([
        'url' => 'Ce site est déjà enregistré. Contactez support@seo-autopilot.com si vous êtes le propriétaire.'
    ]);
}
```

### Quota Verification

```php
// App\Services\Billing\QuotaService

public function canCreateSite(Team $team): bool
{
    $limit = $team->plan->sites_limit;
    return $limit === -1 || $team->sites()->count() < $limit;
}

public function canGenerateArticle(Team $team): bool
{
    if ($team->isTrialExpired()) return false;

    $used = $team->articlesUsedThisMonth();
    return $used < $team->plan->articles_per_month;
}

public function getRemainingAllocation(Site $site): int
{
    $teamTotal = $site->team->plan->articles_per_month;
    $allocatedToOthers = $site->team->sites()
        ->where('id', '!=', $site->id)
        ->sum('site_settings.articles_allocated');

    return $teamTotal - $allocatedToOthers;
}
```

### Account Freeze (Trial Expired)

```php
// Middleware CheckSubscription

public function handle($request, Closure $next)
{
    $team = $request->user()->currentTeam;

    if ($team->isTrialExpired() && !$team->subscribed()) {
        // Allow read-only
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        // Block write actions (except billing)
        if (!$request->routeIs('billing.*')) {
            return redirect()->route('settings.billing')
                ->with('warning', 'Votre période d\'essai est terminée.');
        }
    }

    return $next($request);
}
```

---

## Stripe Integration

### Payment Flow

```
User clicks "Upgrade"
        ↓
Create Stripe Checkout Session
        ↓
Redirect to Stripe
        ↓
Payment success → Webhook checkout.session.completed
        ↓
Update Team (plan_id, is_trial=false)
```

### Routes

```php
// routes/web.php
Route::middleware('auth')->group(function () {
    Route::get('/billing', [BillingController::class, 'index']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::get('/billing/success', [BillingController::class, 'success']);
    Route::get('/billing/cancel', [BillingController::class, 'cancel']);
    Route::post('/billing/portal', [BillingController::class, 'portal']);
});

// Webhook (no auth)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
```

### Webhooks to Handle

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Activate subscription, update plan_id |
| `customer.subscription.updated` | Handle upgrade/downgrade |
| `customer.subscription.deleted` | Revoke access → freeze account |
| `invoice.payment_failed` | Notify user, grace period |

### BillingController

```php
public function checkout(Request $request)
{
    $plan = Plan::findOrFail($request->plan_id);
    $team = $request->user()->currentTeam;

    $checkout = $team->newSubscription('default', $plan->stripe_price_id)
        ->checkout([
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
        ]);

    return Inertia::location($checkout->url);
}

public function portal(Request $request)
{
    return Inertia::location(
        $request->user()->currentTeam->billingPortalUrl(route('settings.billing'))
    );
}
```

---

## Frontend

### Billing Page (Settings/Billing.tsx)

```
┌─────────────────────────────────────────────────────┐
│  Votre abonnement                                   │
│  ┌───────────────────────────────────────────────┐  │
│  │ Plan Pro - $99/mois                           │  │
│  │ 30 articles/mois • 3 sites                    │  │
│  │ Prochain renouvellement: 15 jan 2025          │  │
│  │                      [Gérer l'abonnement]     │  │
│  └───────────────────────────────────────────────┘  │
│                                                     │
│  Changer de plan                                    │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐            │
│  │ Starter │  │   Pro   │  │ Agency  │            │
│  │  $39    │  │   $99   │  │  $249   │            │
│  │ 8 art.  │  │ 30 art. │  │ 100 art.│            │
│  │ 1 site  │  │ 3 sites │  │ Illimité│            │
│  │[Choisir]│  │ Actuel  │  │[Upgrade]│            │
│  └─────────┘  └─────────┘  └─────────┘            │
└─────────────────────────────────────────────────────┘
```

### Article Allocation (Sites/Settings.tsx)

```
┌─────────────────────────────────────────────────────┐
│  Allocation d'articles                              │
│                                                     │
│  Quota disponible: 30/mois (15 restants à allouer) │
│                                                     │
│  Ce site: [====== 15 ======] articles/mois         │
│                                                     │
│  ⚠️ Avec 3 jours de publication, cela fait         │
│     ~5 articles par jour de publication            │
└─────────────────────────────────────────────────────┘
```

---

## PlanSeeder

```php
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
                'features' => ['8 articles/mois', '1 site', 'Support email'],
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name' => 'Pro',
                'price' => 99,
                'articles_per_month' => 30,
                'sites_limit' => 3,
                'features' => ['30 articles/mois', '3 sites', 'Support prioritaire', 'Analytics avancés'],
                'sort_order' => 2,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agency',
                'price' => 249,
                'articles_per_month' => 100,
                'sites_limit' => -1,
                'features' => ['100 articles/mois', 'Sites illimités', 'Support dédié', 'API access'],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                [...$plan, 'features' => json_encode($plan['features'])]
            );
        }
    }
}
```

---

## Implementation Checklist

### Phase 1: Database & Models
- [ ] Create `plans` migration
- [ ] Modify `teams` migration (add plan_id, trial_ends_at, is_trial)
- [ ] Modify `site_settings` migration (add articles_allocated)
- [ ] Create Plan model
- [ ] Update Team model (relations, helper methods)
- [ ] Create PlanSeeder

### Phase 2: Stripe Setup
- [ ] Create products/prices in Stripe Dashboard
- [ ] Add stripe_price_id to plans via seeder or migration
- [ ] Configure webhook endpoint in Stripe

### Phase 3: Backend
- [ ] Create QuotaService
- [ ] Create BillingController
- [ ] Create StripeWebhookController
- [ ] Create CheckSubscription middleware
- [ ] Add domain check in OnboardingController

### Phase 4: Frontend
- [ ] Create Settings/Billing.tsx page
- [ ] Add allocation UI in site settings
- [ ] Update onboarding to show trial info
- [ ] Add upgrade CTAs in frozen state

### Phase 5: Testing
- [ ] Test trial flow
- [ ] Test checkout flow
- [ ] Test webhook handling
- [ ] Test upgrade/downgrade
- [ ] Test account freeze
