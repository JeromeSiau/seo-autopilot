<?php

namespace App\Services\Notification;

use App\Models\ApprovalRequest;
use App\Models\ArticleAssignment;
use App\Models\EditorialComment;
use App\Models\Notification;
use App\Models\User;
use App\Models\Site;
use App\Models\Article;
use Illuminate\Support\Collection;
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

    public function notifyAiVisibilityAlert(Site $site, array $payload): void
    {
        $user = $site->team->owner;
        $summary = $payload['summary'] ?? [];
        $highRiskPrompts = (int) ($summary['high_risk_prompts'] ?? 0);
        $decliningChecks = (int) ($summary['declining_checks'] ?? 0);
        $alerts = collect($payload['alerts'] ?? []);

        if ($highRiskPrompts === 0 && $decliningChecks === 0 && $alerts->isEmpty()) {
            return;
        }

        $recentDuplicate = Notification::query()
            ->where('user_id', $user->id)
            ->where('site_id', $site->id)
            ->where('type', Notification::TYPE_AI_VISIBILITY_ALERT)
            ->where('created_at', '>=', now()->subHours(6))
            ->exists();

        if ($recentDuplicate) {
            return;
        }

        $message = trim(collect([
            $highRiskPrompts > 0 ? "{$highRiskPrompts} prompts at risk" : null,
            $decliningChecks > 0 ? "{$decliningChecks} declining checks" : null,
            $alerts->isNotEmpty() ? 'AI visibility requires editorial follow-up' : null,
        ])->filter()->implode(', '));

        Notification::notify(
            userId: $user->id,
            type: Notification::TYPE_AI_VISIBILITY_ALERT,
            title: "AI visibility risk on {$site->name}",
            message: $message !== '' ? $message . '.' : 'AI visibility requires attention.',
            siteId: $site->id,
            actionUrl: route('analytics.ai-visibility.index', ['site_id' => $site->id]),
        );
    }

    public function notifyEditorialComment(EditorialComment $comment, User $actor): void
    {
        $article = $comment->article->loadMissing(['site.team.users', 'assignments']);
        $recipients = $this->editorialRecipients($article, $actor, includePendingApprover: true);

        $this->notifyUsers(
            $recipients,
            Notification::TYPE_EDITORIAL_COMMENT,
            "New comment on “{$article->title}”",
            "\"{$actor->name}\" added a comment in the editorial thread.",
            $article->site_id,
            route('articles.show', $article->id),
        );
    }

    public function notifyAssignment(ArticleAssignment $assignment, User $actor): void
    {
        $assignment->loadMissing(['article.site', 'user']);

        if (!$assignment->user || $assignment->user->id === $actor->id) {
            return;
        }

        Notification::notify(
            userId: $assignment->user->id,
            type: Notification::TYPE_ASSIGNMENT,
            title: "Assigned as {$assignment->role}",
            message: "\"{$actor->name}\" assigned you to “{$assignment->article->title}”.",
            siteId: $assignment->article->site_id,
            actionUrl: route('articles.show', $assignment->article_id),
        );
    }

    public function notifyApprovalRequested(ApprovalRequest $approvalRequest, User $actor): void
    {
        $approvalRequest->loadMissing(['article.site', 'requestedTo']);

        if (!$approvalRequest->requestedTo || $approvalRequest->requestedTo->id === $actor->id) {
            return;
        }

        Notification::notify(
            userId: $approvalRequest->requestedTo->id,
            type: Notification::TYPE_APPROVAL_REQUESTED,
            title: "Approval requested for “{$approvalRequest->article->title}”",
            message: "\"{$actor->name}\" requested your approval.",
            siteId: $approvalRequest->article->site_id,
            actionUrl: route('articles.show', $approvalRequest->article_id),
        );
    }

    public function notifyApprovalDecision(ApprovalRequest $approvalRequest, User $actor): void
    {
        $approvalRequest->loadMissing(['article.site', 'requestedBy']);

        if (!$approvalRequest->requestedBy || $approvalRequest->requestedBy->id === $actor->id) {
            return;
        }

        $decision = $approvalRequest->status === ApprovalRequest::STATUS_APPROVED ? 'approved' : 'rejected';

        Notification::notify(
            userId: $approvalRequest->requestedBy->id,
            type: Notification::TYPE_APPROVAL_DECIDED,
            title: "Approval {$decision} for “{$approvalRequest->article->title}”",
            message: "\"{$actor->name}\" {$decision} the approval request.",
            siteId: $approvalRequest->article->site_id,
            actionUrl: route('articles.show', $approvalRequest->article_id),
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

    protected function editorialRecipients(Article $article, User $actor, bool $includePendingApprover = false): Collection
    {
        $article->loadMissing(['site.team.users', 'assignments.user', 'approvalRequests.requestedTo']);

        $recipients = collect([$article->site->team->owner])
            ->merge($article->assignments->map->user)
            ->filter();

        if ($includePendingApprover) {
            $recipients = $recipients->merge(
                $article->approvalRequests
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->map->requestedTo
                    ->filter()
            );
        }

        return $recipients
            ->filter(fn (User $user) => $user->id !== $actor->id)
            ->unique('id')
            ->values();
    }

    protected function notifyUsers(
        Collection $users,
        string $type,
        string $title,
        ?string $message = null,
        ?int $siteId = null,
        ?string $actionUrl = null,
    ): void {
        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            Notification::notify(
                userId: $user->id,
                type: $type,
                title: $title,
                message: $message,
                siteId: $siteId,
                actionUrl: $actionUrl,
            );
        }
    }

    private function sendImmediateEmail(User $user, string $subject, string ...$lines): void
    {
        try {
            Mail::raw(implode("\n", $lines), function ($message) use ($user, $subject) {
                $message->to($user->email)
                    ->subject("[RankCruise] {$subject}");
            });
        } catch (\Exception $e) {
            \Log::error("Failed to send immediate email", ['error' => $e->getMessage()]);
        }
    }
}
