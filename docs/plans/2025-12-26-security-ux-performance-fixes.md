# Security, UX & Performance Fixes Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Fix critical security vulnerabilities, improve accessibility, and resolve N+1 query performance issues identified in the comprehensive audit.

**Architecture:** This plan addresses issues across the Laravel backend (Models, Controllers, Policies, Routes) and React frontend (Components, Pages). Each fix is isolated and can be committed independently. We follow TDD where applicable and prioritize critical security issues first.

**Tech Stack:** Laravel 12, React 18, TypeScript, Inertia.js, Tailwind CSS, Redis

---

## Phase 1: Critical Security Fixes

### Task 1: Fix XSS Vulnerability in Article Display

**Files:**
- Modify: `resources/js/Pages/Articles/Show.tsx:129-132`
- Modify: `package.json` (add dompurify)

**Step 1: Install DOMPurify**

Run:
```bash
npm install dompurify @types/dompurify
```

Expected: Package added to package.json

**Step 2: Run npm to verify installation**

Run:
```bash
npm ls dompurify
```

Expected: `dompurify@X.X.X` shown in tree

**Step 3: Update Article Show component to sanitize HTML**

Modify `resources/js/Pages/Articles/Show.tsx`:

Find:
```tsx
<div
    className="prose prose-sm max-w-none dark:prose-invert"
    dangerouslySetInnerHTML={{ __html: article.content }}
/>
```

Replace with:
```tsx
import DOMPurify from 'dompurify';

// ... in component, before return:
const sanitizedContent = DOMPurify.sanitize(article.content, {
    ALLOWED_TAGS: ['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'a', 'ul', 'ol', 'li', 'strong', 'em', 'code', 'pre', 'blockquote', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'br', 'hr', 'span', 'div'],
    ALLOWED_ATTR: ['href', 'src', 'alt', 'title', 'class', 'id', 'target', 'rel'],
});

// ... in JSX:
<div
    className="prose prose-sm max-w-none dark:prose-invert"
    dangerouslySetInnerHTML={{ __html: sanitizedContent }}
/>
```

**Step 4: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 5: Commit**

```bash
git add package.json package-lock.json resources/js/Pages/Articles/Show.tsx
git commit -m "security: fix XSS vulnerability with DOMPurify sanitization"
```

---

### Task 2: Add API Rate Limiting

**Files:**
- Modify: `routes/api.php`
- Modify: `bootstrap/app.php`

**Step 1: Configure rate limiter in bootstrap**

Modify `bootstrap/app.php`, add after the existing middleware configuration:

Find the `withMiddleware` section and ensure rate limiting is configured:
```php
->withMiddleware(function (Middleware $middleware) {
    // ... existing code
})
```

Note: Laravel 12 has rate limiting configured by default. We just need to apply it to API routes.

**Step 2: Apply throttle middleware to API routes**

Modify `routes/api.php`:

Find:
```php
Route::middleware('auth:sanctum')->group(function () {
```

Replace with:
```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
```

This limits to 60 requests per minute per user.

**Step 3: Add stricter rate limit for sensitive endpoints**

Add before the main group in `routes/api.php`:
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// At the top of the file or in a service provider
// For now, we'll use the default throttle which is sufficient
```

**Step 4: Verify syntax**

Run:
```bash
php -l routes/api.php
```

Expected: `No syntax errors detected`

**Step 5: Commit**

```bash
git add routes/api.php
git commit -m "security: add rate limiting to API routes (60/min)"
```

---

### Task 3: Remove is_admin from User fillable

**Files:**
- Modify: `app/Models/User.php:35`

**Step 1: Update User model fillable array**

Modify `app/Models/User.php`:

Find:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'current_team_id',
    'theme',
    'locale',
    'is_admin',
    'email_notifications',
    'push_notifications',
];
```

Replace with:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'current_team_id',
    'theme',
    'locale',
    'email_notifications',
    'push_notifications',
];

protected $guarded = [
    'is_admin',
];
```

**Step 2: Verify syntax**

Run:
```bash
php -l app/Models/User.php
```

Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "security: move is_admin to guarded to prevent mass assignment"
```

---

### Task 4: Create ArticlePolicy

**Files:**
- Create: `app/Policies/ArticlePolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create ArticlePolicy file**

Create `app/Policies/ArticlePolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ArticlePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }

    public function approve(User $user, Article $article): bool
    {
        return $user->currentTeam?->id === $article->site->team_id;
    }
}
```

**Step 2: Register the policy**

Modify `app/Providers/AppServiceProvider.php`, add in the `boot()` method:
```php
use App\Models\Article;
use App\Policies\ArticlePolicy;
use Illuminate\Support\Facades\Gate;

// In boot() method:
Gate::policy(Article::class, ArticlePolicy::class);
```

**Step 3: Verify syntax**

Run:
```bash
php -l app/Policies/ArticlePolicy.php && php -l app/Providers/AppServiceProvider.php
```

Expected: `No syntax errors detected` for both files

**Step 4: Commit**

```bash
git add app/Policies/ArticlePolicy.php app/Providers/AppServiceProvider.php
git commit -m "security: add ArticlePolicy for authorization"
```

---

### Task 5: Create KeywordPolicy

**Files:**
- Create: `app/Policies/KeywordPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

**Step 1: Create KeywordPolicy file**

Create `app/Policies/KeywordPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\Keyword;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class KeywordPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    public function create(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    public function update(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    public function delete(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }

    public function generate(User $user, Keyword $keyword): bool
    {
        return $user->currentTeam?->id === $keyword->site->team_id;
    }
}
```

**Step 2: Register the policy**

Modify `app/Providers/AppServiceProvider.php`, add imports and policy registration:
```php
use App\Models\Keyword;
use App\Policies\KeywordPolicy;

// In boot() method, add:
Gate::policy(Keyword::class, KeywordPolicy::class);
```

**Step 3: Verify syntax**

Run:
```bash
php -l app/Policies/KeywordPolicy.php
```

Expected: `No syntax errors detected`

**Step 4: Commit**

```bash
git add app/Policies/KeywordPolicy.php app/Providers/AppServiceProvider.php
git commit -m "security: add KeywordPolicy for authorization"
```

---

### Task 6: Fix Authorization Inconsistency in ContentPlanController

**Files:**
- Modify: `app/Http/Controllers/Api/ContentPlanController.php:19,46`

**Step 1: Fix team_id check to use current_team_id**

Modify `app/Http/Controllers/Api/ContentPlanController.php`:

Find (around line 19):
```php
if ($site->team_id !== auth()->user()->team_id) {
    abort(403);
}
```

Replace with:
```php
if ($site->team_id !== auth()->user()->current_team_id) {
    abort(403);
}
```

Find (around line 46):
```php
if ($site->team_id !== auth()->user()->team_id) {
    abort(403);
}
```

Replace with:
```php
if ($site->team_id !== auth()->user()->current_team_id) {
    abort(403);
}
```

**Step 2: Verify syntax**

Run:
```bash
php -l app/Http/Controllers/Api/ContentPlanController.php
```

Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/Api/ContentPlanController.php
git commit -m "security: fix auth check to use current_team_id"
```

---

## Phase 2: Critical Performance Fixes

### Task 7: Fix N+1 Queries in DashboardController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

**Step 1: Refactor to use withCount instead of iteration**

Modify `app/Http/Controllers/DashboardController.php`:

Find the entire `index` method and replace with:
```php
public function index()
{
    $user = auth()->user();
    $team = $user->currentTeam;

    if (! $team) {
        return redirect()->route('teams.create');
    }

    $sites = $team->sites()
        ->withCount([
            'keywords as total_keywords_count',
            'keywords as queued_keywords_count' => fn ($q) => $q->where('status', 'queued'),
            'articles as total_articles_count',
            'articles as review_articles_count' => fn ($q) => $q->where('status', 'review'),
            'articles as this_month_articles_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfMonth()),
            'articles as this_week_articles_count' => fn ($q) => $q->where('created_at', '>=', now()->startOfWeek()),
        ])
        ->with(['settings'])
        ->get();

    $stats = [
        'total_sites' => $sites->count(),
        'total_keywords' => $sites->sum('total_keywords_count'),
        'keywords_in_queue' => $sites->sum('queued_keywords_count'),
        'total_articles' => $sites->sum('total_articles_count'),
        'articles_this_month' => $sites->sum('this_month_articles_count'),
    ];

    $sitesData = $sites->map(fn ($site) => [
        'id' => $site->id,
        'name' => $site->name,
        'domain' => $site->domain,
        'keywords_count' => $site->total_keywords_count,
        'articles_count' => $site->total_articles_count,
        'articles_in_review' => $site->review_articles_count,
        'articles_this_week' => $site->this_week_articles_count,
        'autopilot_enabled' => $site->settings?->autopilot_enabled ?? false,
        'onboarding_completed' => $site->isOnboardingComplete(),
    ]);

    return Inertia::render('Dashboard', [
        'stats' => $stats,
        'sites' => $sitesData,
    ]);
}
```

**Step 2: Verify syntax**

Run:
```bash
php -l app/Http/Controllers/DashboardController.php
```

Expected: `No syntax errors detected`

**Step 3: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "perf: fix N+1 queries in DashboardController with withCount"
```

---

### Task 8: Add Database Indexes for Frequent Queries

**Files:**
- Create: `database/migrations/2025_12_26_000001_add_performance_indexes.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration add_performance_indexes
```

**Step 2: Add index definitions**

Edit the newly created migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->index('created_at');
            $table->index(['site_id', 'status']);
            $table->index(['site_id', 'created_at']);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->index('score');
            $table->index(['site_id', 'status']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->index('read_at');
            $table->index(['user_id', 'read_at']);
        });

        Schema::table('agent_events', function (Blueprint $table) {
            $table->index(['article_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['site_id', 'status']);
            $table->dropIndex(['site_id', 'created_at']);
        });

        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex(['score']);
            $table->dropIndex(['site_id', 'status']);
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex(['read_at']);
            $table->dropIndex(['user_id', 'read_at']);
        });

        Schema::table('agent_events', function (Blueprint $table) {
            $table->dropIndex(['article_id', 'created_at']);
        });
    }
};
```

**Step 3: Run migration**

Run:
```bash
php artisan migrate
```

Expected: Migration runs successfully

**Step 4: Commit**

```bash
git add database/migrations/*add_performance_indexes*
git commit -m "perf: add database indexes for frequent queries"
```

---

### Task 9: Add Caching for Team Articles Count

**Files:**
- Modify: `app/Models/Team.php:112-120`

**Step 1: Add cache to articlesUsedThisMonth method**

Modify `app/Models/Team.php`:

Find:
```php
public function articlesUsedThisMonth(): int
{
    return Article::whereIn('site_id', $this->sites->pluck('id'))
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();
}
```

Replace with:
```php
use Illuminate\Support\Facades\Cache;

public function articlesUsedThisMonth(): int
{
    $cacheKey = "team:{$this->id}:articles_month:" . now()->format('Y-m');

    return Cache::remember($cacheKey, 300, function () {
        return Article::whereIn('site_id', $this->sites->pluck('id'))
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
    });
}

public function clearArticlesMonthCache(): void
{
    $cacheKey = "team:{$this->id}:articles_month:" . now()->format('Y-m');
    Cache::forget($cacheKey);
}
```

**Step 2: Clear cache when article is created**

Modify `app/Models/Article.php`, add a boot method:
```php
protected static function boot()
{
    parent::boot();

    static::created(function (Article $article) {
        $article->site->team->clearArticlesMonthCache();
    });

    static::deleted(function (Article $article) {
        $article->site->team->clearArticlesMonthCache();
    });
}
```

**Step 3: Verify syntax**

Run:
```bash
php -l app/Models/Team.php && php -l app/Models/Article.php
```

Expected: `No syntax errors detected`

**Step 4: Commit**

```bash
git add app/Models/Team.php app/Models/Article.php
git commit -m "perf: cache articlesUsedThisMonth with auto-invalidation"
```

---

### Task 10: Switch to Redis Cache in Production

**Files:**
- Modify: `.env.example`

**Step 1: Update .env.example with Redis as default cache**

Modify `.env.example`:

Find:
```
CACHE_STORE=database
```

Replace with:
```
CACHE_STORE=redis
```

**Step 2: Add documentation comment**

Add after the CACHE_STORE line:
```
# Use 'database' for development, 'redis' for production
```

**Step 3: Commit**

```bash
git add .env.example
git commit -m "perf: set Redis as default cache driver for production"
```

---

## Phase 3: Accessibility Fixes

### Task 11: Add aria-labels to Icon Buttons in Sites/Index

**Files:**
- Modify: `resources/js/Pages/Sites/Index.tsx`

**Step 1: Add aria-labels to all icon-only buttons**

Modify `resources/js/Pages/Sites/Index.tsx`:

Find delete button (look for `<Trash2`):
```tsx
<button
    onClick={(e) => handleDelete(e, site)}
    className="rounded-lg p-2 text-surface-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
>
    <Trash2 className="h-4 w-4" />
</button>
```

Replace with:
```tsx
<button
    onClick={(e) => handleDelete(e, site)}
    className="rounded-lg p-2 text-surface-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20"
    aria-label={`Delete site ${site.domain}`}
>
    <Trash2 className="h-4 w-4" />
</button>
```

Find any other icon buttons and add appropriate aria-labels.

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Pages/Sites/Index.tsx
git commit -m "a11y: add aria-labels to icon buttons in Sites/Index"
```

---

### Task 12: Add aria-labels to Icon Buttons in Articles/Index

**Files:**
- Modify: `resources/js/Pages/Articles/Index.tsx`

**Step 1: Add aria-labels to all icon-only buttons**

Modify `resources/js/Pages/Articles/Index.tsx`:

For each icon-only button, add an appropriate `aria-label` attribute:
- Edit button: `aria-label={`Edit article ${article.title}`}`
- Delete button: `aria-label={`Delete article ${article.title}`}`
- View button: `aria-label={`View article ${article.title}`}`

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Pages/Articles/Index.tsx
git commit -m "a11y: add aria-labels to icon buttons in Articles/Index"
```

---

### Task 13: Fix Dropdown Component for Keyboard Navigation

**Files:**
- Modify: `resources/js/Components/Dropdown.tsx`

**Step 1: Replace div trigger with button and add keyboard support**

Modify `resources/js/Components/Dropdown.tsx`:

Find the Trigger component:
```tsx
const Trigger = ({ children }: PropsWithChildren) => {
    return (
        <div onClick={toggleOpen}>{children}</div>
    );
};
```

Replace with:
```tsx
const Trigger = ({ children }: PropsWithChildren) => {
    const handleKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleOpen();
        }
        if (e.key === 'Escape' && open) {
            setOpen(false);
        }
    };

    return (
        <button
            type="button"
            onClick={toggleOpen}
            onKeyDown={handleKeyDown}
            aria-expanded={open}
            aria-haspopup="true"
            className="inline-flex items-center"
        >
            {children}
        </button>
    );
};
```

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Components/Dropdown.tsx
git commit -m "a11y: add keyboard navigation to Dropdown component"
```

---

### Task 14: Add ARIA attributes to TeamSwitcher

**Files:**
- Modify: `resources/js/Components/TeamSwitcher.tsx`

**Step 1: Add ARIA attributes to the dropdown trigger**

Modify `resources/js/Components/TeamSwitcher.tsx`:

Find the main button:
```tsx
<button
    onClick={() => setIsOpen(!isOpen)}
    className="..."
>
```

Replace with:
```tsx
<button
    onClick={() => setIsOpen(!isOpen)}
    onKeyDown={(e) => {
        if (e.key === 'Escape') setIsOpen(false);
    }}
    aria-expanded={isOpen}
    aria-haspopup="listbox"
    aria-label="Switch team"
    className="..."
>
```

Add `role="listbox"` to the dropdown menu container and `role="option"` to each team item.

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Components/TeamSwitcher.tsx
git commit -m "a11y: add ARIA attributes to TeamSwitcher"
```

---

### Task 15: Fix Form Label Associations in IntegrationForm

**Files:**
- Modify: `resources/js/Components/Integration/IntegrationForm.tsx`

**Step 1: Add id to inputs and htmlFor to labels**

Modify `resources/js/Components/Integration/IntegrationForm.tsx`:

For each input/label pair, ensure they are properly associated:

Example pattern:
```tsx
<label htmlFor="integration-name" className="...">
    Name
</label>
<input
    id="integration-name"
    type="text"
    value={data.name}
    onChange={(e) => setData('name', e.target.value)}
    className="..."
/>
```

Apply this pattern to all form fields: name, url, username, password, api_key, etc.

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Components/Integration/IntegrationForm.tsx
git commit -m "a11y: associate form labels with inputs in IntegrationForm"
```

---

## Phase 4: UX Improvements

### Task 16: Update EmptyState for Dark Mode

**Files:**
- Modify: `resources/js/Components/ui/EmptyState.tsx`

**Step 1: Replace gray colors with surface colors**

Modify `resources/js/Components/ui/EmptyState.tsx`:

Find:
```tsx
className="border-gray-300 bg-white"
```

Replace with:
```tsx
className="border-surface-300 bg-white dark:border-surface-600 dark:bg-surface-800"
```

Find:
```tsx
<Icon className="h-6 w-6 text-gray-400" />
```

Replace with:
```tsx
<Icon className="h-6 w-6 text-surface-400" />
```

Update all other `gray-*` classes to `surface-*` equivalents with dark mode variants.

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Components/ui/EmptyState.tsx
git commit -m "ui: update EmptyState component for dark mode support"
```

---

### Task 17: Update Badge Component for Dark Mode

**Files:**
- Modify: `resources/js/Components/ui/Badge.tsx`

**Step 1: Update variant classes with dark mode**

Modify `resources/js/Components/ui/Badge.tsx`:

Find the variant classes object and update:
```tsx
const variantClasses = {
    default: 'bg-surface-100 text-surface-700 dark:bg-surface-700 dark:text-surface-200',
    success: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    warning: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    danger: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    info: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    primary: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
};
```

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Commit**

```bash
git add resources/js/Components/ui/Badge.tsx
git commit -m "ui: update Badge component for dark mode support"
```

---

### Task 18: Create Toast Component for Flash Messages

**Files:**
- Create: `resources/js/Components/ui/Toast.tsx`
- Modify: `resources/js/Layouts/AppLayout.tsx`

**Step 1: Create Toast component**

Create `resources/js/Components/ui/Toast.tsx`:
```tsx
import { useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { X, CheckCircle, XCircle, AlertCircle } from 'lucide-react';
import { clsx } from 'clsx';

interface ToastProps {
    message: string;
    type: 'success' | 'error' | 'warning';
    onClose: () => void;
}

function Toast({ message, type, onClose }: ToastProps) {
    useEffect(() => {
        const timer = setTimeout(onClose, 5000);
        return () => clearTimeout(timer);
    }, [onClose]);

    const icons = {
        success: CheckCircle,
        error: XCircle,
        warning: AlertCircle,
    };
    const Icon = icons[type];

    const styles = {
        success: 'bg-green-50 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        error: 'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        warning: 'bg-yellow-50 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    };

    return (
        <div className={clsx('flex items-center gap-3 rounded-lg px-4 py-3 shadow-lg', styles[type])}>
            <Icon className="h-5 w-5 flex-shrink-0" />
            <p className="text-sm font-medium">{message}</p>
            <button
                onClick={onClose}
                className="ml-auto rounded p-1 hover:bg-black/5 dark:hover:bg-white/5"
                aria-label="Close notification"
            >
                <X className="h-4 w-4" />
            </button>
        </div>
    );
}

export function ToastContainer() {
    const { flash } = usePage<{ flash?: { success?: string; error?: string } }>().props;
    const [toasts, setToasts] = useState<Array<{ id: number; message: string; type: 'success' | 'error' }>>([]);

    useEffect(() => {
        if (flash?.success) {
            setToasts((prev) => [...prev, { id: Date.now(), message: flash.success!, type: 'success' }]);
        }
        if (flash?.error) {
            setToasts((prev) => [...prev, { id: Date.now(), message: flash.error!, type: 'error' }]);
        }
    }, [flash?.success, flash?.error]);

    const removeToast = (id: number) => {
        setToasts((prev) => prev.filter((t) => t.id !== id));
    };

    if (toasts.length === 0) return null;

    return (
        <div className="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
            {toasts.map((toast) => (
                <Toast
                    key={toast.id}
                    message={toast.message}
                    type={toast.type}
                    onClose={() => removeToast(toast.id)}
                />
            ))}
        </div>
    );
}
```

**Step 2: Add ToastContainer to AppLayout**

Modify `resources/js/Layouts/AppLayout.tsx`:

Add import:
```tsx
import { ToastContainer } from '@/Components/ui/Toast';
```

Add before closing `</div>` of the main container:
```tsx
<ToastContainer />
```

**Step 3: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 4: Commit**

```bash
git add resources/js/Components/ui/Toast.tsx resources/js/Layouts/AppLayout.tsx
git commit -m "ui: add Toast component for flash message feedback"
```

---

### Task 19: Replace Native confirm() with Modal in Sites/Index

**Files:**
- Modify: `resources/js/Pages/Sites/Index.tsx`

**Step 1: Add state for delete confirmation modal**

Modify `resources/js/Pages/Sites/Index.tsx`:

Add state and Modal component:
```tsx
import Modal from '@/Components/Modal';
import { Button } from '@/Components/ui/Button';

// Inside component:
const [deleteModal, setDeleteModal] = useState<{ open: boolean; site: Site | null }>({
    open: false,
    site: null,
});

const handleDelete = (site: Site) => {
    setDeleteModal({ open: true, site });
};

const confirmDelete = () => {
    if (deleteModal.site) {
        router.delete(route('sites.destroy', { site: deleteModal.site.id }), {
            onSuccess: () => setDeleteModal({ open: false, site: null }),
        });
    }
};
```

**Step 2: Add Modal JSX**

Add before the closing tag:
```tsx
<Modal
    show={deleteModal.open}
    onClose={() => setDeleteModal({ open: false, site: null })}
    maxWidth="sm"
>
    <div className="p-6">
        <h3 className="text-lg font-semibold text-surface-900 dark:text-white">
            Delete Site
        </h3>
        <p className="mt-2 text-sm text-surface-600 dark:text-surface-400">
            Are you sure you want to delete {deleteModal.site?.domain}? This action cannot be undone.
        </p>
        <div className="mt-6 flex justify-end gap-3">
            <Button
                variant="secondary"
                onClick={() => setDeleteModal({ open: false, site: null })}
            >
                Cancel
            </Button>
            <Button variant="danger" onClick={confirmDelete}>
                Delete
            </Button>
        </div>
    </div>
</Modal>
```

**Step 3: Update the delete button click handler**

Change:
```tsx
onClick={(e) => {
    e.stopPropagation();
    if (confirm(...)) { ... }
}}
```

To:
```tsx
onClick={(e) => {
    e.stopPropagation();
    handleDelete(site);
}}
```

**Step 4: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 5: Commit**

```bash
git add resources/js/Pages/Sites/Index.tsx
git commit -m "ui: replace native confirm() with styled Modal for site deletion"
```

---

### Task 20: Add Vite Code Splitting Configuration

**Files:**
- Modify: `vite.config.js`

**Step 1: Add rollup output configuration for code splitting**

Modify `vite.config.js`:

Replace entire file with:
```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.tsx',
            refresh: true,
        }),
        tailwindcss(),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    vendor: ['react', 'react-dom'],
                    inertia: ['@inertiajs/react'],
                    ui: ['lucide-react', '@headlessui/react', 'clsx'],
                },
            },
        },
    },
});
```

**Step 2: Verify build works**

Run:
```bash
npm run build
```

Expected: Build completes successfully with separate chunks

**Step 3: Commit**

```bash
git add vite.config.js
git commit -m "perf: add Vite code splitting for vendor chunks"
```

---

## Final Verification

### Task 21: Run Full Test Suite and Build

**Step 1: Run PHP linting**

Run:
```bash
find app -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
```

Expected: No output (all files pass)

**Step 2: Run TypeScript check**

Run:
```bash
npm run typecheck
```

Expected: No TypeScript errors

**Step 3: Run production build**

Run:
```bash
npm run build
```

Expected: Build completes successfully

**Step 4: Create summary commit**

```bash
git add -A
git status
```

Review any uncommitted changes and commit if needed.

---

## Summary

This plan addresses:

**Security (5 tasks):**
- XSS fix with DOMPurify
- API rate limiting
- Mass assignment protection
- ArticlePolicy and KeywordPolicy
- Auth consistency fix

**Performance (4 tasks):**
- N+1 query fix in DashboardController
- Database indexes
- Team articles caching
- Redis cache configuration

**Accessibility (5 tasks):**
- Icon button aria-labels
- Dropdown keyboard navigation
- TeamSwitcher ARIA
- Form label associations

**UX (5 tasks):**
- EmptyState dark mode
- Badge dark mode
- Toast notifications
- Modal for confirmations
- Vite code splitting

Total: 21 tasks
