<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshSiteArticleScoresJob;
use App\Models\ApprovalRequest;
use App\Models\Article;
use App\Models\ArticleAssignment;
use App\Models\EditorialComment;
use App\Models\User;
use App\Services\Notification\NotificationService;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ArticleWorkflowController extends Controller
{
    public function storeComment(Request $request, Article $article, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('comment', $article);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $article->editorialComments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $notifications->notifyEditorialComment($comment->loadMissing('article'), $request->user());

        return back()->with('success', 'Comment added.');
    }

    public function resolveComment(Request $request, Article $article, EditorialComment $editorialComment): RedirectResponse
    {
        $this->authorize('comment', $article);
        abort_unless($editorialComment->article_id === $article->id, 404);

        $user = $request->user();
        abort_unless(
            $editorialComment->user_id === $user->id || $user->isOwnerOrAdminOfTeam($article->site->team),
            403
        );

        $editorialComment->update([
            'resolved_at' => $editorialComment->resolved_at ? null : now(),
        ]);

        return back()->with('success', $editorialComment->resolved_at ? 'Comment resolved.' : 'Comment reopened.');
    }

    public function storeAssignment(Request $request, Article $article, NotificationService $notifications): RedirectResponse
    {
        $this->authorize('assign', $article);

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'role' => ['required', Rule::in(ArticleAssignment::ROLES)],
        ]);

        abort_unless($this->userBelongsToArticleTeam((int) $validated['user_id'], $article), 422);

        $assignment = $article->assignments()->updateOrCreate(
            ['role' => $validated['role']],
            [
                'user_id' => $validated['user_id'],
                'assigned_at' => now(),
            ],
        );

        $notifications->notifyAssignment($assignment->loadMissing(['article.site', 'user']), $request->user());

        return back()->with('success', 'Assignment saved.');
    }

    public function destroyAssignment(Article $article, ArticleAssignment $articleAssignment): RedirectResponse
    {
        $this->authorize('assign', $article);
        abort_unless($articleAssignment->article_id === $article->id, 404);

        $articleAssignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    public function requestApproval(Request $request, Article $article, NotificationService $notifications, WebhookDispatcher $webhooks): RedirectResponse
    {
        $this->authorize('requestApproval', $article);

        $validated = $request->validate([
            'requested_to' => ['required', 'exists:users,id'],
            'decision_note' => ['nullable', 'string', 'max:5000'],
        ]);

        abort_unless($this->userBelongsToArticleTeam((int) $validated['requested_to'], $article), 422);

        if ($article->approvalRequests()->where('status', ApprovalRequest::STATUS_PENDING)->exists()) {
            return back()->with('error', 'There is already a pending approval request for this article.');
        }

        $approvalRequest = $article->approvalRequests()->create([
            'requested_by' => $request->user()->id,
            'requested_to' => $validated['requested_to'],
            'status' => ApprovalRequest::STATUS_PENDING,
            'decision_note' => $validated['decision_note'] ?? null,
        ]);

        $article->assignments()->updateOrCreate(
            ['role' => ArticleAssignment::ROLE_APPROVER],
            [
                'user_id' => $validated['requested_to'],
                'assigned_at' => now(),
            ],
        );

        $notifications->notifyApprovalRequested($approvalRequest->loadMissing(['article.site', 'requestedTo']), $request->user());
        $webhooks->dispatch($article->site->team, 'approval.requested', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'title' => $article->title,
            'approval_request_id' => $approvalRequest->id,
            'requested_by' => $approvalRequest->requested_by,
            'requested_to' => $approvalRequest->requested_to,
            'status' => $approvalRequest->status,
        ]);

        return back()->with('success', 'Approval request sent.');
    }

    public function approveRequest(Request $request, Article $article, ApprovalRequest $approvalRequest, WebhookDispatcher $webhooks, NotificationService $notifications): RedirectResponse
    {
        abort_unless($approvalRequest->article_id === $article->id, 404);
        abort_unless($this->canDecideApprovalRequest($request->user(), $article, $approvalRequest), 403);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_APPROVED,
            'decision_note' => $validated['decision_note'] ?? $approvalRequest->decision_note,
            'decided_at' => now(),
        ]);

        if (in_array($article->status, [Article::STATUS_DRAFT, Article::STATUS_REVIEW], true)) {
            $article->markAsApproved();
        }

        $webhooks->dispatch($article->site->team, 'article.approved', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'title' => $article->title,
            'approval_request_id' => $approvalRequest->id,
            'status' => $article->fresh()->status,
        ]);
        $webhooks->dispatch($article->site->team, 'approval.approved', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'title' => $article->title,
            'approval_request_id' => $approvalRequest->id,
            'requested_by' => $approvalRequest->requested_by,
            'requested_to' => $approvalRequest->requested_to,
            'status' => $approvalRequest->status,
        ]);

        $notifications->notifyApprovalDecision($approvalRequest->fresh(['article.site', 'requestedBy']), $request->user());

        RefreshSiteArticleScoresJob::dispatch($article->site);

        return back()->with('success', 'Approval recorded.');
    }

    public function rejectRequest(Request $request, Article $article, ApprovalRequest $approvalRequest, NotificationService $notifications, WebhookDispatcher $webhooks): RedirectResponse
    {
        abort_unless($approvalRequest->article_id === $article->id, 404);
        abort_unless($this->canDecideApprovalRequest($request->user(), $article, $approvalRequest), 403);

        $validated = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:5000'],
        ]);

        $approvalRequest->update([
            'status' => ApprovalRequest::STATUS_REJECTED,
            'decision_note' => $validated['decision_note'] ?? $approvalRequest->decision_note,
            'decided_at' => now(),
        ]);

        if ($article->status === Article::STATUS_DRAFT) {
            $article->markAsReview();
        }

        $webhooks->dispatch($article->site->team, 'approval.rejected', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'title' => $article->title,
            'approval_request_id' => $approvalRequest->id,
            'requested_by' => $approvalRequest->requested_by,
            'requested_to' => $approvalRequest->requested_to,
            'status' => $approvalRequest->status,
        ]);
        $notifications->notifyApprovalDecision($approvalRequest->fresh(['article.site', 'requestedBy']), $request->user());
        RefreshSiteArticleScoresJob::dispatch($article->site);

        return back()->with('success', 'Approval request rejected.');
    }

    protected function userBelongsToArticleTeam(int $userId, Article $article): bool
    {
        return $article->site->team->users()->where('users.id', $userId)->exists();
    }

    protected function canDecideApprovalRequest(User $user, Article $article, ApprovalRequest $approvalRequest): bool
    {
        if ($user->isOwnerOrAdminOfTeam($article->site->team)) {
            return true;
        }

        return $approvalRequest->requested_to === $user->id;
    }
}
