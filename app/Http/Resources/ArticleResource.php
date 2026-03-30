<?php

namespace App\Http\Resources;

use App\Services\Analytics\BusinessAttributionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => $this->when($request->routeIs('articles.show'), $this->content),
            'excerpt' => $this->meta_description,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'images' => $this->images,
            'featured_image_url' => $this->featured_image_url,
            'status' => $this->status,
            'hosted_author' => $this->whenLoaded('hostedAuthor', fn () => (new HostedAuthorResource($this->hostedAuthor))->resolve()),
            'hosted_category' => $this->whenLoaded('hostedCategory', fn () => (new HostedCategoryResource($this->hostedCategory))->resolve()),
            'hosted_tags' => $this->whenLoaded('hostedTags', fn () => HostedTagResource::collection($this->hostedTags)->resolve()),
            'word_count' => $this->word_count,
            'llm_used' => $this->llm_used,
            'generation_cost' => $this->generation_cost,
            'generation_time_seconds' => $this->generation_time_seconds,
            'published_at' => $this->published_at,
            'published_url' => $this->published_url,
            'published_via' => $this->published_via,
            'error_message' => $this->when($this->status === 'failed', $this->error_message),
            'keyword' => $this->whenLoaded('keyword', fn () => (new KeywordResource($this->keyword))->resolve()),
            'site' => $this->whenLoaded('site', fn () => (new SiteResource($this->site))->resolve()),
            'score' => $this->whenLoaded('score', fn () => (new ArticleScoreResource($this->score))->resolve()),
            'citations' => $this->whenLoaded('citations', fn () => ArticleCitationResource::collection($this->citations)->resolve()),
            'editorial_comments' => $this->whenLoaded('editorialComments', fn () => EditorialCommentResource::collection($this->editorialComments)->resolve()),
            'assignments' => $this->whenLoaded('assignments', fn () => ArticleAssignmentResource::collection($this->assignments)->resolve()),
            'approval_requests' => $this->whenLoaded('approvalRequests', fn () => ApprovalRequestResource::collection($this->approvalRequests)->resolve()),
            'refresh_recommendations' => $this->whenLoaded('refreshRecommendations', fn () => RefreshRecommendationResource::collection($this->refreshRecommendations)->resolve()),
            'latest_refresh_run' => $this->whenLoaded('refreshRuns', fn () => optional($this->refreshRuns->first(), fn ($run) => (new ArticleRefreshRunResource($run))->resolve())),
            'activity_timeline' => $this->when(
                $request->routeIs('articles.show'),
                fn () => $this->activityTimeline(),
            ),
            'permissions' => $request->user() ? [
                'update' => $request->user()->can('update', $this->resource),
                'delete' => $request->user()->can('delete', $this->resource),
                'approve' => $request->user()->can('approve', $this->resource),
                'publish' => $request->user()->can('publish', $this->resource),
                'comment' => $request->user()->can('comment', $this->resource),
                'assign' => $request->user()->can('assign', $this->resource),
                'request_approval' => $request->user()->can('requestApproval', $this->resource),
            ] : null,
            'analytics' => [
                'total_clicks' => $this->total_clicks,
                'total_impressions' => $this->total_impressions,
                'total_sessions' => $this->total_sessions,
                'total_page_views' => $this->total_page_views,
                'total_conversions' => $this->total_conversions,
                'estimated_conversions' => $this->estimated_conversions,
                'conversion_source' => $this->conversion_source,
                'conversion_rate' => $this->conversion_rate,
                'avg_position' => $this->average_position,
                'estimated_value' => $this->estimated_value,
                'roi' => $this->roi,
            ],
            'business_attribution' => $this->when(
                $request->routeIs('articles.show'),
                fn () => app(BusinessAttributionService::class)->summarizeArticle($this->resource),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    protected function activityTimeline(): array
    {
        $events = collect();

        if ($this->relationLoaded('editorialComments')) {
            $events = $events->merge($this->editorialComments->map(fn ($comment) => [
                'type' => 'comment',
                'title' => 'Comment added',
                'body' => $comment->body,
                'created_at' => optional($comment->created_at)?->toIso8601String(),
                'actor' => $comment->user ? [
                    'id' => $comment->user->id,
                    'name' => $comment->user->name,
                    'email' => $comment->user->email,
                ] : null,
            ]));
        }

        if ($this->relationLoaded('assignments')) {
            $events = $events->merge($this->assignments->map(fn ($assignment) => [
                'type' => 'assignment',
                'title' => "Assigned {$assignment->role}",
                'body' => $assignment->user?->name ? "{$assignment->user->name} assigned as {$assignment->role}." : null,
                'created_at' => optional($assignment->assigned_at ?? $assignment->created_at)?->toIso8601String(),
                'actor' => $assignment->user ? [
                    'id' => $assignment->user->id,
                    'name' => $assignment->user->name,
                    'email' => $assignment->user->email,
                ] : null,
            ]));
        }

        if ($this->relationLoaded('approvalRequests')) {
            $events = $events->merge($this->approvalRequests->map(function ($approvalRequest) {
                $statusLabel = ucfirst((string) $approvalRequest->status);
                $actor = $approvalRequest->status === 'pending'
                    ? $approvalRequest->requested_by_user
                    : $approvalRequest->requested_to_user;

                return [
                    'type' => 'approval',
                    'title' => "Approval {$statusLabel}",
                    'body' => $approvalRequest->requested_to_user?->name
                        ? "Requested for {$approvalRequest->requested_to_user->name}."
                        : $approvalRequest->decision_note,
                    'created_at' => optional($approvalRequest->decided_at ?? $approvalRequest->created_at)?->toIso8601String(),
                    'actor' => $actor ? [
                        'id' => $actor->id,
                        'name' => $actor->name,
                        'email' => $actor->email,
                    ] : null,
                ];
            }));
        }

        if ($this->relationLoaded('refreshRuns')) {
            $events = $events->merge($this->refreshRuns->map(fn ($run) => [
                'type' => 'refresh',
                'title' => 'Refresh draft generated',
                'body' => $run->summary,
                'created_at' => optional($run->created_at)?->toIso8601String(),
                'actor' => null,
            ]));
        }

        return $events
            ->filter(fn (array $event) => filled($event['created_at']))
            ->sortByDesc('created_at')
            ->values()
            ->all();
    }
}
