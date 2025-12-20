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
            ->whereNull('read_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function markAllAsRead(User $user): void
    {
        $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    private function sendImmediateEmail(User $user, string $subject, string ...$lines): void
    {
        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($user, $subject) {
                $message->to($user->email)
                    ->subject("[SEO Autopilot] {$subject}");
            });
        } catch (\Exception $e) {
            \Log::error("Failed to send immediate email", ['error' => $e->getMessage()]);
        }
    }
}
