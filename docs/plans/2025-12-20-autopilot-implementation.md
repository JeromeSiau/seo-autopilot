# Autopilot Full-Auto Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transformer l'interface manuelle en système 100% automatisé avec wizard d'onboarding et dashboard multi-sites.

**Architecture:**
- Backend: Jobs Laravel schedulés (discovery, generation, publishing) + Services dédiés
- Frontend: Wizard multi-étapes Inertia/React + Dashboard global avec drill-down
- Notifications: Table dédiée + emails via Laravel Mail

**Tech Stack:** Laravel 12, Inertia.js, React, MySQL, Laravel Horizon

---

## Phase 1: Base de Données

### Task 1.1: Migration site_settings

**Files:**
- Create: `database/migrations/2025_12_20_160000_create_site_settings_table.php`

**Step 1: Créer la migration**

```bash
php artisan make:migration create_site_settings_table
```

**Step 2: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->boolean('autopilot_enabled')->default(false);
            $table->unsignedTinyInteger('articles_per_week')->default(5);
            $table->json('publish_days')->default('["mon","tue","wed","thu","fri","sat","sun"]');
            $table->boolean('auto_publish')->default(true);
            $table->timestamps();

            $table->unique('site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
```

**Step 3: Exécuter la migration**

```bash
php artisan migrate
```
Expected: Migration successful

**Step 4: Commit**

```bash
git add database/migrations/*site_settings*
git commit -m "feat(db): add site_settings table for autopilot config"
```

---

### Task 1.2: Migration autopilot_logs

**Files:**
- Create: `database/migrations/2025_12_20_160001_create_autopilot_logs_table.php`

**Step 1: Créer la migration**

```bash
php artisan make:migration create_autopilot_logs_table
```

**Step 2: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autopilot_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 50); // keyword_discovered, article_generated, article_published, publish_failed, keywords_imported
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['site_id', 'created_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autopilot_logs');
    }
};
```

**Step 3: Exécuter la migration**

```bash
php artisan migrate
```

**Step 4: Commit**

```bash
git add database/migrations/*autopilot_logs*
git commit -m "feat(db): add autopilot_logs table for activity tracking"
```

---

### Task 1.3: Migration notifications

**Files:**
- Create: `database/migrations/2025_12_20_160002_create_notifications_table.php`

**Step 1: Créer la migration**

```bash
php artisan make:migration create_notifications_table
```

**Step 2: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50); // review_needed, published, publish_failed, quota_warning, keywords_found
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('action_url')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

**Step 3: Exécuter et committer**

```bash
php artisan migrate
git add database/migrations/*notifications*
git commit -m "feat(db): add notifications table"
```

---

### Task 1.4: Migration sites (colonnes supplémentaires)

**Files:**
- Create: `database/migrations/2025_12_20_160003_add_onboarding_fields_to_sites_table.php`

**Step 1: Créer la migration**

```bash
php artisan make:migration add_onboarding_fields_to_sites_table
```

**Step 2: Écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->text('business_description')->nullable()->after('language');
            $table->string('target_audience')->nullable()->after('business_description');
            $table->json('topics')->nullable()->after('target_audience');
            $table->timestamp('last_crawled_at')->nullable()->after('topics');
            $table->timestamp('onboarding_completed_at')->nullable()->after('last_crawled_at');
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn([
                'business_description',
                'target_audience',
                'topics',
                'last_crawled_at',
                'onboarding_completed_at',
            ]);
        });
    }
};
```

**Step 3: Exécuter et committer**

```bash
php artisan migrate
git add database/migrations/*onboarding_fields*
git commit -m "feat(db): add onboarding fields to sites table"
```

---

### Task 1.5: Migration keywords (colonnes queue)

**Files:**
- Create: `database/migrations/2025_12_20_160004_add_queue_fields_to_keywords_table.php`

**Step 1: Créer et écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->timestamp('queued_at')->nullable()->after('score');
            $table->timestamp('processed_at')->nullable()->after('queued_at');
            $table->unsignedInteger('priority')->default(0)->after('processed_at');

            $table->index(['site_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('keywords', function (Blueprint $table) {
            $table->dropIndex(['site_id', 'status', 'priority']);
            $table->dropColumn(['queued_at', 'processed_at', 'priority']);
        });
    }
};
```

**Step 2: Exécuter et committer**

```bash
php artisan migrate
git add database/migrations/*queue_fields*
git commit -m "feat(db): add queue fields to keywords table"
```

---

### Task 1.6: Migration users (préférences notifications)

**Files:**
- Create: `database/migrations/2025_12_20_160005_add_notification_prefs_to_users_table.php`

**Step 1: Créer et écrire la migration**

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
            $table->string('notification_email_frequency', 20)->default('weekly')->after('remember_token'); // daily, weekly, disabled
            $table->boolean('notification_immediate_failures')->default(true)->after('notification_email_frequency');
            $table->boolean('notification_immediate_quota')->default(true)->after('notification_immediate_failures');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'notification_email_frequency',
                'notification_immediate_failures',
                'notification_immediate_quota',
            ]);
        });
    }
};
```

**Step 2: Exécuter et committer**

```bash
php artisan migrate
git add database/migrations/*notification_prefs*
git commit -m "feat(db): add notification preferences to users table"
```

---

## Phase 2: Models Eloquent

### Task 2.1: Model SiteSetting

**Files:**
- Create: `app/Models/SiteSetting.php`

**Step 1: Créer le model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteSetting extends Model
{
    protected $fillable = [
        'site_id',
        'autopilot_enabled',
        'articles_per_week',
        'publish_days',
        'auto_publish',
    ];

    protected $casts = [
        'autopilot_enabled' => 'boolean',
        'articles_per_week' => 'integer',
        'publish_days' => 'array',
        'auto_publish' => 'boolean',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function canPublishToday(): bool
    {
        $today = strtolower(now()->format('D')); // mon, tue, wed...
        return in_array($today, $this->publish_days ?? []);
    }

    public function getDefaultArticlesPerWeek(): int
    {
        $team = $this->site->team;
        $monthlyLimit = $team->articles_limit ?? 30;

        return match(true) {
            $monthlyLimit <= 10 => 2,   // Starter
            $monthlyLimit <= 30 => 7,   // Pro
            default => 25,               // Agency
        };
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/SiteSetting.php
git commit -m "feat(models): add SiteSetting model"
```

---

### Task 2.2: Model AutopilotLog

**Files:**
- Create: `app/Models/AutopilotLog.php`

**Step 1: Créer le model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutopilotLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'site_id',
        'event_type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public const TYPE_KEYWORD_DISCOVERED = 'keyword_discovered';
    public const TYPE_ARTICLE_GENERATED = 'article_generated';
    public const TYPE_ARTICLE_PUBLISHED = 'article_published';
    public const TYPE_PUBLISH_FAILED = 'publish_failed';
    public const TYPE_KEYWORDS_IMPORTED = 'keywords_imported';

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public static function log(int $siteId, string $eventType, array $payload = []): self
    {
        return self::create([
            'site_id' => $siteId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }

    public function scopeRecent($query, int $limit = 20)
    {
        return $query->orderByDesc('created_at')->limit($limit);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/AutopilotLog.php
git commit -m "feat(models): add AutopilotLog model"
```

---

### Task 2.3: Model Notification

**Files:**
- Create: `app/Models/Notification.php`

**Step 1: Créer le model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'site_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public const TYPE_REVIEW_NEEDED = 'review_needed';
    public const TYPE_PUBLISHED = 'published';
    public const TYPE_PUBLISH_FAILED = 'publish_failed';
    public const TYPE_QUOTA_WARNING = 'quota_warning';
    public const TYPE_KEYWORDS_FOUND = 'keywords_found';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function markAsRead(): void
    {
        $this->update(['read_at' => now()]);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public static function notify(
        int $userId,
        string $type,
        string $title,
        ?string $message = null,
        ?int $siteId = null,
        ?string $actionUrl = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'site_id' => $siteId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }
}
```

**Step 2: Commit**

```bash
git add app/Models/Notification.php
git commit -m "feat(models): add Notification model"
```

---

### Task 2.4: Mettre à jour le model Site

**Files:**
- Modify: `app/Models/Site.php`

**Step 1: Ajouter les relations et fillables**

Ajouter dans `$fillable`:
```php
'business_description',
'target_audience',
'topics',
'last_crawled_at',
'onboarding_completed_at',
```

Ajouter dans `$casts`:
```php
'topics' => 'array',
'last_crawled_at' => 'datetime',
'onboarding_completed_at' => 'datetime',
```

Ajouter les relations:
```php
public function settings(): HasOne
{
    return $this->hasOne(SiteSetting::class);
}

public function autopilotLogs(): HasMany
{
    return $this->hasMany(AutopilotLog::class);
}

public function getOrCreateSettings(): SiteSetting
{
    return $this->settings ?? SiteSetting::create([
        'site_id' => $this->id,
        'articles_per_week' => (new SiteSetting(['site_id' => $this->id]))->getDefaultArticlesPerWeek(),
    ]);
}

public function isAutopilotActive(): bool
{
    return $this->settings?->autopilot_enabled ?? false;
}

public function isOnboardingComplete(): bool
{
    return $this->onboarding_completed_at !== null;
}
```

**Step 2: Commit**

```bash
git add app/Models/Site.php
git commit -m "feat(models): add autopilot relations to Site model"
```

---

### Task 2.5: Mettre à jour models User et Keyword

**Files:**
- Modify: `app/Models/User.php`
- Modify: `app/Models/Keyword.php`

**Step 1: User - ajouter relation notifications et fillables**

Dans User.php, ajouter:
```php
// Dans $fillable
'notification_email_frequency',
'notification_immediate_failures',
'notification_immediate_quota',

// Dans $casts
'notification_immediate_failures' => 'boolean',
'notification_immediate_quota' => 'boolean',

// Nouvelle relation
public function notifications(): HasMany
{
    return $this->hasMany(Notification::class);
}

public function unreadNotificationsCount(): int
{
    return $this->notifications()->unread()->count();
}
```

**Step 2: Keyword - ajouter colonnes queue dans fillables et casts**

Dans Keyword.php, ajouter:
```php
// Dans $fillable
'queued_at',
'processed_at',
'priority',

// Dans $casts
'queued_at' => 'datetime',
'processed_at' => 'datetime',
'priority' => 'integer',

// Nouveau scope
public function scopeQueued($query)
{
    return $query->where('status', 'queued')
        ->orderByDesc('priority')
        ->orderBy('queued_at');
}

public function addToQueue(): void
{
    $this->update([
        'status' => 'queued',
        'queued_at' => now(),
        'priority' => $this->calculateScore(),
    ]);
}
```

**Step 3: Commit**

```bash
git add app/Models/User.php app/Models/Keyword.php
git commit -m "feat(models): update User and Keyword for autopilot"
```

---

## Phase 3: Services Backend

### Task 3.1: AutopilotService

**Files:**
- Create: `app/Services/Autopilot/AutopilotService.php`

**Step 1: Créer le service**

```php
<?php

namespace App\Services\Autopilot;

use App\Models\Site;
use App\Models\Keyword;
use App\Models\AutopilotLog;
use App\Jobs\GenerateArticleJob;
use Illuminate\Support\Facades\Log;

class AutopilotService
{
    public function processKeywordDiscovery(Site $site): int
    {
        if (!$site->isAutopilotActive()) {
            return 0;
        }

        $discoveredCount = 0;

        // Import from GSC if connected
        if ($site->isGscConnected()) {
            $discoveredCount += $this->importFromSearchConsole($site);
        }

        // Generate via LLM if we have business description
        if ($site->business_description) {
            $discoveredCount += $this->generateKeywordSuggestions($site);
        }

        if ($discoveredCount > 0) {
            AutopilotLog::log($site->id, AutopilotLog::TYPE_KEYWORDS_IMPORTED, [
                'count' => $discoveredCount,
            ]);
        }

        return $discoveredCount;
    }

    public function processArticleGeneration(Site $site): bool
    {
        $settings = $site->settings;

        if (!$settings?->autopilot_enabled) {
            return false;
        }

        // Check if today is a publish day
        if (!$settings->canPublishToday()) {
            Log::info("AutopilotService: Not a publish day for site {$site->id}");
            return false;
        }

        // Check weekly quota
        $articlesThisWeek = $site->articles()
            ->where('created_at', '>=', now()->startOfWeek())
            ->count();

        if ($articlesThisWeek >= $settings->articles_per_week) {
            Log::info("AutopilotService: Weekly quota reached for site {$site->id}");
            return false;
        }

        // Get next keyword from queue
        $keyword = $site->keywords()
            ->queued()
            ->first();

        if (!$keyword) {
            Log::info("AutopilotService: No keywords in queue for site {$site->id}");
            return false;
        }

        // Dispatch generation job
        $keyword->markAsGenerating();
        GenerateArticleJob::dispatch($keyword);

        AutopilotLog::log($site->id, AutopilotLog::TYPE_ARTICLE_GENERATED, [
            'keyword_id' => $keyword->id,
            'keyword' => $keyword->keyword,
        ]);

        return true;
    }

    public function getActiveSites(): \Illuminate\Database\Eloquent\Collection
    {
        return Site::whereHas('settings', function ($query) {
            $query->where('autopilot_enabled', true);
        })->get();
    }

    private function importFromSearchConsole(Site $site): int
    {
        // Utilise le service existant
        $service = app(\App\Services\Keyword\KeywordDiscoveryService::class);
        return $service->discoverFromSearchConsole($site);
    }

    private function generateKeywordSuggestions(Site $site): int
    {
        // Utilise le service existant
        $service = app(\App\Services\Keyword\KeywordDiscoveryService::class);
        return $service->generateFromBusinessDescription($site);
    }
}
```

**Step 2: Commit**

```bash
mkdir -p app/Services/Autopilot
git add app/Services/Autopilot/AutopilotService.php
git commit -m "feat(services): add AutopilotService"
```

---

### Task 3.2: NotificationService

**Files:**
- Create: `app/Services/Notification/NotificationService.php`

**Step 1: Créer le service**

```php
<?php

namespace App\Services\Notification;

use App\Models\Notification;
use App\Models\User;
use App\Models\Site;
use App\Models\Article;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    public function notifyArticleReady(Article $article): void
    {
        $site = $article->site;
        $user = $site->team->owner;

        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_REVIEW_NEEDED,
            title: "Article en attente de review",
            message: "\"{$article->title}\" est prêt pour validation",
            siteId: $site->id,
            actionUrl: route('articles.show', $article->id)
        );
    }

    public function notifyArticlePublished(Article $article): void
    {
        $site = $article->site;
        $user = $site->team->owner;

        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_PUBLISHED,
            title: "Article publié",
            message: "\"{$article->title}\" a été publié avec succès",
            siteId: $site->id,
            actionUrl: $article->published_url ?? route('articles.show', $article->id)
        );
    }

    public function notifyPublishFailed(Article $article, string $error): void
    {
        $site = $article->site;
        $user = $site->team->owner;

        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_PUBLISH_FAILED,
            title: "Échec de publication",
            message: "Erreur pour \"{$article->title}\": {$error}",
            siteId: $site->id,
            actionUrl: route('articles.show', $article->id)
        );

        // Send immediate email if enabled
        if ($user->notification_immediate_failures) {
            $this->sendImmediateEmail($user, 'Échec de publication', $article->title, $error);
        }
    }

    public function notifyQuotaWarning(User $user, int $percentUsed): void
    {
        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_QUOTA_WARNING,
            title: "Quota presque atteint",
            message: "Vous avez utilisé {$percentUsed}% de votre quota mensuel",
            actionUrl: route('settings.billing')
        );

        if ($user->notification_immediate_quota) {
            $this->sendImmediateEmail($user, 'Quota presque atteint', "Utilisation: {$percentUsed}%");
        }
    }

    public function notifyKeywordsFound(Site $site, int $count): void
    {
        $user = $site->team->owner;

        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_KEYWORDS_FOUND,
            title: "{$count} nouveaux keywords découverts",
            message: "Nouveaux keywords ajoutés à la queue pour {$site->domain}",
            siteId: $site->id,
            actionUrl: route('sites.show', $site->id)
        );
    }

    public function getUnreadForUser(User $user, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return $user->notifications()
            ->unread()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function markAllAsRead(User $user): void
    {
        $user->notifications()
            ->unread()
            ->update(['read_at' => now()]);
    }

    private function sendImmediateEmail(User $user, string $subject, string ...$lines): void
    {
        // Simple email - can be enhanced with Mailable later
        Mail::raw(implode("\n", $lines), function ($message) use ($user, $subject) {
            $message->to($user->email)
                ->subject("[SEO Autopilot] {$subject}");
        });
    }
}
```

**Step 2: Commit**

```bash
mkdir -p app/Services/Notification
git add app/Services/Notification/NotificationService.php
git commit -m "feat(services): add NotificationService"
```

---

## Phase 4: Jobs Autopilot

### Task 4.1: AutopilotDiscoveryJob

**Files:**
- Create: `app/Jobs/AutopilotDiscoveryJob.php`

**Step 1: Créer le job**

```php
<?php

namespace App\Jobs;

use App\Services\Autopilot\AutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 300;

    public function handle(AutopilotService $autopilot): void
    {
        Log::info('AutopilotDiscoveryJob: Starting keyword discovery for all active sites');

        $sites = $autopilot->getActiveSites();
        $totalDiscovered = 0;

        foreach ($sites as $site) {
            try {
                $count = $autopilot->processKeywordDiscovery($site);
                $totalDiscovered += $count;

                Log::info("AutopilotDiscoveryJob: Discovered {$count} keywords for site {$site->domain}");
            } catch (\Exception $e) {
                Log::error("AutopilotDiscoveryJob: Failed for site {$site->domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotDiscoveryJob: Completed. Total discovered: {$totalDiscovered}");
    }

    public function tags(): array
    {
        return ['autopilot', 'discovery'];
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/AutopilotDiscoveryJob.php
git commit -m "feat(jobs): add AutopilotDiscoveryJob"
```

---

### Task 4.2: AutopilotGenerationJob

**Files:**
- Create: `app/Jobs/AutopilotGenerationJob.php`

**Step 1: Créer le job**

```php
<?php

namespace App\Jobs;

use App\Services\Autopilot\AutopilotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    public function handle(AutopilotService $autopilot): void
    {
        Log::info('AutopilotGenerationJob: Checking for articles to generate');

        $sites = $autopilot->getActiveSites();
        $generated = 0;

        foreach ($sites as $site) {
            try {
                if ($autopilot->processArticleGeneration($site)) {
                    $generated++;
                }
            } catch (\Exception $e) {
                Log::error("AutopilotGenerationJob: Failed for site {$site->domain}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotGenerationJob: Dispatched {$generated} article generation jobs");
    }

    public function tags(): array
    {
        return ['autopilot', 'generation'];
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/AutopilotGenerationJob.php
git commit -m "feat(jobs): add AutopilotGenerationJob"
```

---

### Task 4.3: AutopilotPublishJob

**Files:**
- Create: `app/Jobs/AutopilotPublishJob.php`

**Step 1: Créer le job**

```php
<?php

namespace App\Jobs;

use App\Models\Article;
use App\Models\AutopilotLog;
use App\Services\Notification\NotificationService;
use App\Services\Publisher\PublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutopilotPublishJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function handle(PublisherManager $publisher, NotificationService $notifications): void
    {
        Log::info('AutopilotPublishJob: Checking for articles to publish');

        $articles = Article::where('status', 'ready')
            ->whereHas('site.settings', fn($q) => $q->where('auto_publish', true))
            ->get();

        foreach ($articles as $article) {
            try {
                $this->publishArticle($article, $publisher, $notifications);
            } catch (\Exception $e) {
                Log::error("AutopilotPublishJob: Failed for article {$article->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info("AutopilotPublishJob: Processed {$articles->count()} articles");
    }

    private function publishArticle(
        Article $article,
        PublisherManager $publisher,
        NotificationService $notifications
    ): void {
        $site = $article->site;
        $integration = $site->integrations()->where('is_active', true)->first();

        if (!$integration) {
            // No integration - just mark as ready (manual download)
            Log::info("AutopilotPublishJob: No integration for site {$site->id}, keeping as ready");
            return;
        }

        // Dispatch the existing PublishArticleJob
        PublishArticleJob::dispatch($article, $integration);

        AutopilotLog::log($site->id, AutopilotLog::TYPE_ARTICLE_PUBLISHED, [
            'article_id' => $article->id,
            'title' => $article->title,
        ]);
    }

    public function tags(): array
    {
        return ['autopilot', 'publish'];
    }
}
```

**Step 2: Commit**

```bash
git add app/Jobs/AutopilotPublishJob.php
git commit -m "feat(jobs): add AutopilotPublishJob"
```

---

### Task 4.4: Configurer le Scheduler

**Files:**
- Create: `routes/console.php` (ou modifier si existe)

**Step 1: Configurer les commandes schedulées**

```php
<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutopilotDiscoveryJob;
use App\Jobs\AutopilotGenerationJob;
use App\Jobs\AutopilotPublishJob;

// Keyword discovery - once daily at 6 AM
Schedule::job(new AutopilotDiscoveryJob)->dailyAt('06:00');

// Article generation - every hour from 8 AM to 8 PM
Schedule::job(new AutopilotGenerationJob)->hourly()->between('8:00', '20:00');

// Publishing - every hour
Schedule::job(new AutopilotPublishJob)->hourly();
```

**Step 2: Commit**

```bash
git add routes/console.php
git commit -m "feat(scheduler): configure autopilot jobs schedule"
```

---

## Phase 5: Controllers API

### Task 5.1: OnboardingController

**Files:**
- Create: `app/Http/Controllers/Web/OnboardingController.php`

**Step 1: Créer le controller**

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnboardingController extends Controller
{
    public function create()
    {
        return Inertia::render('Onboarding/Wizard', [
            'team' => auth()->user()->team,
        ]);
    }

    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'domain' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'language' => 'required|string|size:2',
        ]);

        $site = Site::create([
            'team_id' => auth()->user()->team_id,
            ...$validated,
        ]);

        return response()->json(['site_id' => $site->id]);
    }

    public function storeStep2(Request $request, Site $site)
    {
        // GSC connection handled separately via OAuth
        // This just marks as skipped if user chooses to skip
        if ($request->boolean('skip')) {
            return response()->json(['skipped' => true]);
        }

        return response()->json(['redirect' => route('auth.google', ['site_id' => $site->id])]);
    }

    public function storeStep3(Request $request, Site $site)
    {
        $validated = $request->validate([
            'business_description' => 'required|string|max:2000',
            'target_audience' => 'nullable|string|max:500',
            'topics' => 'nullable|array',
            'topics.*' => 'string|max:100',
        ]);

        $site->update($validated);

        return response()->json(['success' => true]);
    }

    public function storeStep4(Request $request, Site $site)
    {
        $validated = $request->validate([
            'articles_per_week' => 'required|integer|min:1|max:30',
            'publish_days' => 'required|array|min:1',
            'publish_days.*' => 'string|in:mon,tue,wed,thu,fri,sat,sun',
            'auto_publish' => 'required|boolean',
        ]);

        SiteSetting::updateOrCreate(
            ['site_id' => $site->id],
            $validated
        );

        return response()->json(['success' => true]);
    }

    public function storeStep5(Request $request, Site $site)
    {
        // Integration is optional - handled by existing IntegrationController
        if ($request->boolean('skip')) {
            return response()->json(['skipped' => true]);
        }

        return response()->json([
            'redirect' => route('integrations.create', ['site_id' => $site->id])
        ]);
    }

    public function complete(Site $site)
    {
        $site->update(['onboarding_completed_at' => now()]);

        // Enable autopilot
        $site->settings()->update(['autopilot_enabled' => true]);

        // Queue initial keyword discovery
        dispatch(new \App\Jobs\DiscoverKeywordsJob($site));

        return redirect()->route('dashboard')
            ->with('success', 'Autopilot activé ! La découverte de keywords a commencé.');
    }
}
```

**Step 2: Ajouter les routes**

Dans `routes/web.php`:
```php
// Onboarding Wizard
Route::get('/onboarding', [OnboardingController::class, 'create'])->name('onboarding.create');
Route::post('/onboarding/step1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
Route::post('/onboarding/{site}/step2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
Route::post('/onboarding/{site}/step3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
Route::post('/onboarding/{site}/step4', [OnboardingController::class, 'storeStep4'])->name('onboarding.step4');
Route::post('/onboarding/{site}/step5', [OnboardingController::class, 'storeStep5'])->name('onboarding.step5');
Route::post('/onboarding/{site}/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Web/OnboardingController.php routes/web.php
git commit -m "feat(controllers): add OnboardingController with wizard steps"
```

---

### Task 5.2: NotificationController

**Files:**
- Create: `app/Http/Controllers/Web/NotificationController.php`

**Step 1: Créer le controller**

```php
<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notifications
    ) {}

    public function index()
    {
        $user = auth()->user();

        return response()->json([
            'notifications' => $user->notifications()
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(),
            'unread_count' => $user->unreadNotificationsCount(),
        ]);
    }

    public function markAsRead(Request $request, int $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllAsRead()
    {
        $this->notifications->markAllAsRead(auth()->user());

        return response()->json(['success' => true]);
    }
}
```

**Step 2: Ajouter les routes**

```php
// Notifications
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Web/NotificationController.php routes/web.php
git commit -m "feat(controllers): add NotificationController"
```

---

### Task 5.3: Mettre à jour DashboardController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`

**Step 1: Refactoriser pour dashboard global multi-sites**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $team = $user->team;

        $sites = $team->sites()->with(['settings', 'keywords', 'articles'])->get();

        // Aggregate stats
        $stats = [
            'total_sites' => $sites->count(),
            'active_sites' => $sites->filter(fn($s) => $s->isAutopilotActive())->count(),
            'total_keywords_queued' => $sites->sum(fn($s) => $s->keywords()->where('status', 'queued')->count()),
            'articles_this_month' => $sites->sum(fn($s) => $s->articles()->where('created_at', '>=', now()->startOfMonth())->count()),
            'articles_published_this_month' => $sites->sum(fn($s) => $s->articles()->where('status', 'published')->where('published_at', '>=', now()->startOfMonth())->count()),
            'articles_used' => $team->articlesUsedThisMonth(),
            'articles_limit' => $team->articles_limit,
        ];

        // Sites with status
        $sitesData = $sites->map(fn($site) => [
            'id' => $site->id,
            'domain' => $site->domain,
            'name' => $site->name,
            'autopilot_status' => $this->getAutopilotStatus($site),
            'articles_per_week' => $site->settings?->articles_per_week ?? 0,
            'articles_in_review' => $site->articles()->where('status', 'review')->count(),
            'articles_this_week' => $site->articles()->where('created_at', '>=', now()->startOfWeek())->count(),
            'onboarding_complete' => $site->isOnboardingComplete(),
        ]);

        // Actions required
        $actionsRequired = $this->getActionsRequired($sites);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'sites' => $sitesData,
            'actionsRequired' => $actionsRequired,
            'unreadNotifications' => $user->unreadNotificationsCount(),
        ]);
    }

    private function getAutopilotStatus(Site $site): string
    {
        if (!$site->isOnboardingComplete()) {
            return 'not_configured';
        }

        if (!$site->isAutopilotActive()) {
            return 'paused';
        }

        $hasErrors = $site->articles()
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->exists();

        if ($hasErrors) {
            return 'error';
        }

        return 'active';
    }

    private function getActionsRequired($sites): array
    {
        $actions = [];

        foreach ($sites as $site) {
            $reviewCount = $site->articles()->where('status', 'review')->count();
            if ($reviewCount > 0) {
                $actions[] = [
                    'type' => 'review',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $reviewCount,
                    'message' => "{$reviewCount} article(s) en attente de review",
                    'action_url' => route('sites.show', $site->id) . '?tab=review',
                ];
            }

            $failedCount = $site->articles()->where('status', 'failed')->count();
            if ($failedCount > 0) {
                $actions[] = [
                    'type' => 'failed',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'count' => $failedCount,
                    'message' => "Échec de publication ({$failedCount})",
                    'action_url' => route('sites.show', $site->id) . '?tab=failed',
                ];
            }

            if (!$site->isGscConnected() && $site->isOnboardingComplete()) {
                $actions[] = [
                    'type' => 'recommendation',
                    'site_id' => $site->id,
                    'site_domain' => $site->domain,
                    'message' => "Connecter Google Search Console recommandé",
                    'action_url' => route('auth.google', ['site_id' => $site->id]),
                ];
            }
        }

        return $actions;
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/DashboardController.php
git commit -m "refactor(dashboard): update for global multi-site view"
```

---

## Phase 6: Frontend - Wizard Onboarding

### Task 6.1: Composant Wizard principal

**Files:**
- Create: `resources/js/Pages/Onboarding/Wizard.tsx`

**Step 1: Créer le wizard**

```tsx
import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { PageProps, Team } from '@/types';
import Step1Site from './Steps/Step1Site';
import Step2GSC from './Steps/Step2GSC';
import Step3Business from './Steps/Step3Business';
import Step4Config from './Steps/Step4Config';
import Step5Integration from './Steps/Step5Integration';
import Step6Launch from './Steps/Step6Launch';

interface WizardProps extends PageProps {
    team: Team;
}

export default function Wizard({ team }: WizardProps) {
    const [currentStep, setCurrentStep] = useState(1);
    const [siteId, setSiteId] = useState<number | null>(null);
    const [siteData, setSiteData] = useState({
        domain: '',
        name: '',
        language: 'fr',
    });

    const steps = [
        { number: 1, title: 'Site' },
        { number: 2, title: 'Google Search Console' },
        { number: 3, title: 'Business' },
        { number: 4, title: 'Configuration' },
        { number: 5, title: 'Publication' },
        { number: 6, title: 'Lancement' },
    ];

    const nextStep = () => setCurrentStep((s) => Math.min(s + 1, 6));
    const prevStep = () => setCurrentStep((s) => Math.max(s - 1, 1));

    return (
        <div className="min-h-screen bg-gray-50">
            <Head title="Configuration du site" />

            <div className="mx-auto max-w-3xl px-4 py-12">
                {/* Progress bar */}
                <div className="mb-8">
                    <div className="flex justify-between">
                        {steps.map((step) => (
                            <div
                                key={step.number}
                                className={`flex flex-col items-center ${
                                    step.number <= currentStep
                                        ? 'text-indigo-600'
                                        : 'text-gray-400'
                                }`}
                            >
                                <div
                                    className={`flex h-10 w-10 items-center justify-center rounded-full ${
                                        step.number < currentStep
                                            ? 'bg-indigo-600 text-white'
                                            : step.number === currentStep
                                            ? 'border-2 border-indigo-600 text-indigo-600'
                                            : 'border-2 border-gray-300'
                                    }`}
                                >
                                    {step.number < currentStep ? '✓' : step.number}
                                </div>
                                <span className="mt-2 text-xs font-medium">
                                    {step.title}
                                </span>
                            </div>
                        ))}
                    </div>
                    <div className="mt-4 h-2 rounded-full bg-gray-200">
                        <div
                            className="h-2 rounded-full bg-indigo-600 transition-all"
                            style={{ width: `${((currentStep - 1) / 5) * 100}%` }}
                        />
                    </div>
                </div>

                {/* Step content */}
                <div className="rounded-xl bg-white p-8 shadow-sm">
                    {currentStep === 1 && (
                        <Step1Site
                            data={siteData}
                            setData={setSiteData}
                            onNext={(id) => {
                                setSiteId(id);
                                nextStep();
                            }}
                        />
                    )}
                    {currentStep === 2 && siteId && (
                        <Step2GSC
                            siteId={siteId}
                            onNext={nextStep}
                            onBack={prevStep}
                        />
                    )}
                    {currentStep === 3 && siteId && (
                        <Step3Business
                            siteId={siteId}
                            onNext={nextStep}
                            onBack={prevStep}
                        />
                    )}
                    {currentStep === 4 && siteId && (
                        <Step4Config
                            siteId={siteId}
                            team={team}
                            onNext={nextStep}
                            onBack={prevStep}
                        />
                    )}
                    {currentStep === 5 && siteId && (
                        <Step5Integration
                            siteId={siteId}
                            onNext={nextStep}
                            onBack={prevStep}
                        />
                    )}
                    {currentStep === 6 && siteId && (
                        <Step6Launch
                            siteId={siteId}
                            onBack={prevStep}
                        />
                    )}
                </div>
            </div>
        </div>
    );
}
```

**Step 2: Commit**

```bash
mkdir -p resources/js/Pages/Onboarding/Steps
git add resources/js/Pages/Onboarding/Wizard.tsx
git commit -m "feat(frontend): add Wizard main component"
```

---

### Task 6.2: Step1Site component

**Files:**
- Create: `resources/js/Pages/Onboarding/Steps/Step1Site.tsx`

**Step 1: Créer le composant**

```tsx
import { FormEvent, useState } from 'react';
import { Button } from '@/Components/ui/Button';
import axios from 'axios';

interface Props {
    data: { domain: string; name: string; language: string };
    setData: (data: any) => void;
    onNext: (siteId: number) => void;
}

export default function Step1Site({ data, setData, onNext }: Props) {
    const [loading, setLoading] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const handleSubmit = async (e: FormEvent) => {
        e.preventDefault();
        setLoading(true);
        setErrors({});

        try {
            const response = await axios.post(route('onboarding.step1'), data);
            onNext(response.data.site_id);
        } catch (error: any) {
            if (error.response?.data?.errors) {
                setErrors(error.response.data.errors);
            }
        } finally {
            setLoading(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="text-center">
                <h2 className="text-2xl font-bold text-gray-900">
                    Ajouter votre site
                </h2>
                <p className="mt-2 text-gray-600">
                    Commençons par les informations de base
                </p>
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">
                    Domaine
                </label>
                <input
                    type="text"
                    value={data.domain}
                    onChange={(e) => setData({ ...data, domain: e.target.value })}
                    placeholder="monsite.com"
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {errors.domain && (
                    <p className="mt-1 text-sm text-red-600">{errors.domain}</p>
                )}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">
                    Nom du site
                </label>
                <input
                    type="text"
                    value={data.name}
                    onChange={(e) => setData({ ...data, name: e.target.value })}
                    placeholder="Mon Super Site"
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
                {errors.name && (
                    <p className="mt-1 text-sm text-red-600">{errors.name}</p>
                )}
            </div>

            <div>
                <label className="block text-sm font-medium text-gray-700">
                    Langue du contenu
                </label>
                <select
                    value={data.language}
                    onChange={(e) => setData({ ...data, language: e.target.value })}
                    className="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="fr">Français</option>
                    <option value="en">English</option>
                    <option value="de">Deutsch</option>
                    <option value="es">Español</option>
                    <option value="it">Italiano</option>
                </select>
            </div>

            <div className="flex justify-end">
                <Button type="submit" loading={loading}>
                    Continuer
                </Button>
            </div>
        </form>
    );
}
```

**Step 2: Commit**

```bash
git add resources/js/Pages/Onboarding/Steps/Step1Site.tsx
git commit -m "feat(frontend): add Step1Site component"
```

---

### Task 6.3-6.7: Remaining wizard steps

**Files to create:**
- `resources/js/Pages/Onboarding/Steps/Step2GSC.tsx`
- `resources/js/Pages/Onboarding/Steps/Step3Business.tsx`
- `resources/js/Pages/Onboarding/Steps/Step4Config.tsx`
- `resources/js/Pages/Onboarding/Steps/Step5Integration.tsx`
- `resources/js/Pages/Onboarding/Steps/Step6Launch.tsx`

Ces fichiers suivent le même pattern que Step1Site. Chaque step appelle son endpoint correspondant et passe au suivant.

---

## Phase 7: Frontend - Dashboard Global

### Task 7.1: Nouveau Dashboard.tsx

**Files:**
- Modify: `resources/js/Pages/Dashboard.tsx`

Refactoriser complètement pour afficher:
- Stats globales agrégées
- Liste des sites avec status
- Section actions requises
- Barre de progression usage

---

### Task 7.2: Composant SiteCard

**Files:**
- Create: `resources/js/Components/Dashboard/SiteCard.tsx`

Affiche un site avec:
- Status indicator (🟢🟡⚪🔴)
- Config résumée
- Activité récente
- Lien vers détails

---

### Task 7.3: Site Detail Page

**Files:**
- Modify: `resources/js/Pages/Sites/Show.tsx`

Ajouter:
- Timeline d'activité (depuis autopilot_logs)
- Pipeline visuel (keywords → articles → publication)
- Section review
- Configuration inline

---

## Phase 8: Notifications Frontend

### Task 8.1: NotificationDropdown component

**Files:**
- Create: `resources/js/Components/Notifications/NotificationDropdown.tsx`

Dropdown dans le header avec:
- Liste notifications récentes
- Badge compteur unread
- Mark as read
- Link vers détail

---

### Task 8.2: Intégrer dans AppLayout

**Files:**
- Modify: `resources/js/Layouts/AppLayout.tsx`

Ajouter le NotificationDropdown dans le header.

---

## Résumé des Phases

| Phase | Description | Tasks |
|-------|-------------|-------|
| 1 | Base de données | 6 migrations |
| 2 | Models Eloquent | 5 models |
| 3 | Services Backend | 2 services |
| 4 | Jobs Autopilot | 4 jobs + scheduler |
| 5 | Controllers | 3 controllers |
| 6 | Frontend Wizard | 7 composants |
| 7 | Frontend Dashboard | 3 composants |
| 8 | Notifications | 2 composants |

**Total estimé: ~35 tâches**

---

*Plan créé le 2025-12-20*
