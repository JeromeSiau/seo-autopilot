<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\SiteResource;
use App\Models\ApprovalRequest;
use App\Models\Article;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewQueueController extends Controller
{
    public function index(Request $request): Response
    {
        $team = $request->user()->currentTeam;
        $scope = $request->string('scope')->toString() ?: 'all';
        $sites = $team->sites()->get();
        $siteIds = $sites->pluck('id');

        $baseQuery = Article::query()
            ->whereIn('site_id', $siteIds)
            ->whereIn('status', [Article::STATUS_REVIEW, Article::STATUS_APPROVED]);

        if ($request->filled('site_id')) {
            $baseQuery->where('site_id', $request->integer('site_id'));
        }

        $query = (clone $baseQuery)->with([
            'site',
            'keyword',
            'score',
            'refreshRuns',
            'assignments.user',
            'approvalRequests' => fn ($approvalRequests) => $approvalRequests
                ->with(['requestedBy', 'requestedTo'])
                ->latest(),
        ]);

        if ($request->filled('search')) {
            $query->where(function ($search) use ($request) {
                $search->where('title', 'like', '%' . $request->string('search')->toString() . '%')
                    ->orWhereHas('keyword', fn ($keywords) => $keywords->where('keyword', 'like', '%' . $request->string('search')->toString() . '%'));
            });
        }

        $this->applyScope($query, $request->user()->id, $scope);

        $articles = $query
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Articles/ReviewQueue', [
            'articles' => ArticleResource::collection($articles)->response()->getData(true),
            'scope' => $scope,
            'sites' => SiteResource::collection($sites)->resolve(),
            'filters' => $request->only(['site_id', 'search']),
            'stats' => [
                'all' => (clone $baseQuery)->count(),
                'assigned' => (clone $baseQuery)->whereHas('assignments', fn ($assignments) => $assignments->where('user_id', $request->user()->id))->count(),
                'pending' => (clone $baseQuery)->whereHas('approvalRequests', fn ($approvalRequests) => $approvalRequests
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->where('requested_to', $request->user()->id))->count(),
                'requested_by_me' => (clone $baseQuery)->whereHas('approvalRequests', fn ($approvalRequests) => $approvalRequests
                    ->where('status', ApprovalRequest::STATUS_PENDING)
                    ->where('requested_by', $request->user()->id))->count(),
                'unassigned' => (clone $baseQuery)->whereDoesntHave('assignments')->count(),
                'refresh_ready' => (clone $baseQuery)->whereHas('refreshRuns', fn ($refreshRuns) => $refreshRuns->where('status', 'review_ready'))->count(),
                'ready' => (clone $baseQuery)->where(function ($ready) {
                    $ready->where('status', Article::STATUS_APPROVED)
                        ->orWhere(function ($needsApproval) {
                            $needsApproval->where('status', Article::STATUS_REVIEW)
                                ->whereDoesntHave('approvalRequests', fn ($approvalRequests) => $approvalRequests->where('status', ApprovalRequest::STATUS_PENDING))
                                ->whereHas('score', fn ($scores) => $scores->where('readiness_score', '>=', 80));
                        });
                })->count(),
                'blocked' => (clone $baseQuery)
                    ->where(function ($blocked) {
                        $blocked->whereDoesntHave('score')
                            ->orWhereHas('score', fn ($scores) => $scores->where('readiness_score', '<', 80));
                    })
                    ->count(),
            ],
        ]);
    }

    protected function applyScope($query, int $userId, string $scope): void
    {
        match ($scope) {
            'assigned' => $query->whereHas('assignments', fn ($assignments) => $assignments->where('user_id', $userId)),
            'pending' => $query->whereHas('approvalRequests', fn ($approvalRequests) => $approvalRequests
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->where('requested_to', $userId)),
            'requested_by_me' => $query->whereHas('approvalRequests', fn ($approvalRequests) => $approvalRequests
                ->where('status', ApprovalRequest::STATUS_PENDING)
                ->where('requested_by', $userId)),
            'unassigned' => $query->whereDoesntHave('assignments'),
            'refresh_ready' => $query->whereHas('refreshRuns', fn ($refreshRuns) => $refreshRuns->where('status', 'review_ready')),
            'ready' => $query->where(function ($ready) {
                $ready->where('status', Article::STATUS_APPROVED)
                    ->orWhere(function ($needsApproval) {
                        $needsApproval->where('status', Article::STATUS_REVIEW)
                            ->whereDoesntHave('approvalRequests', fn ($approvalRequests) => $approvalRequests->where('status', ApprovalRequest::STATUS_PENDING))
                            ->whereHas('score', fn ($scores) => $scores->where('readiness_score', '>=', 80));
                    });
            }),
            'blocked' => $query->where(function ($blocked) {
                $blocked->whereDoesntHave('score')
                    ->orWhereHas('score', fn ($scores) => $scores->where('readiness_score', '<', 80));
            }),
            default => null,
        };
    }
}
