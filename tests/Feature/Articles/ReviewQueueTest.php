<?php

namespace Tests\Feature\Articles;

use App\Models\ApprovalRequest;
use App\Models\Article;
use App\Models\ArticleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_review_queue_applies_scope_filters(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);

        $assigned = Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Assigned article',
            'status' => Article::STATUS_REVIEW,
        ]);
        $assigned->assignments()->create([
            'user_id' => $user->id,
            'role' => ArticleAssignment::ROLE_REVIEWER,
            'assigned_at' => now(),
        ]);
        $assigned->score()->create([
            'readiness_score' => 88,
            'brand_fit_score' => 85,
            'seo_score' => 88,
            'citation_score' => 82,
            'internal_link_score' => 90,
            'fact_confidence_score' => 92,
            'warnings' => [],
            'checklist' => [],
        ]);

        $pending = Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Pending approval article',
            'status' => Article::STATUS_REVIEW,
        ]);
        $pending->approvalRequests()->create([
            'requested_by' => $user->id,
            'requested_to' => $user->id,
            'status' => ApprovalRequest::STATUS_PENDING,
        ]);
        $pending->score()->create([
            'readiness_score' => 90,
            'brand_fit_score' => 90,
            'seo_score' => 90,
            'citation_score' => 90,
            'internal_link_score' => 90,
            'fact_confidence_score' => 90,
            'warnings' => [],
            'checklist' => [],
        ]);

        $blocked = Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Blocked article',
            'status' => Article::STATUS_REVIEW,
        ]);
        $blocked->score()->create([
            'readiness_score' => 54,
            'brand_fit_score' => 50,
            'seo_score' => 55,
            'citation_score' => 52,
            'internal_link_score' => 60,
            'fact_confidence_score' => 53,
            'warnings' => ['Needs more citations.'],
            'checklist' => [],
        ]);

        $ready = Article::factory()->create([
            'site_id' => $site->id,
            'title' => 'Ready article',
            'status' => Article::STATUS_APPROVED,
        ]);
        $ready->score()->create([
            'readiness_score' => 91,
            'brand_fit_score' => 90,
            'seo_score' => 93,
            'citation_score' => 88,
            'internal_link_score' => 87,
            'fact_confidence_score' => 90,
            'warnings' => [],
            'checklist' => [],
        ]);
        $ready->refreshRuns()->create([
            'refresh_recommendation_id' => null,
            'old_score_snapshot' => [],
            'new_score_snapshot' => [],
            'status' => 'review_ready',
            'summary' => 'Refresh draft reapplied for editorial review.',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->get(route('articles.review-queue', [
            'scope' => 'refresh_ready',
            'search' => 'Ready',
        ]));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Articles/ReviewQueue')
            ->where('scope', 'refresh_ready')
            ->where('filters.search', 'Ready')
            ->where('stats.all', 4)
            ->where('stats.assigned', 1)
            ->where('stats.pending', 1)
            ->where('stats.requested_by_me', 1)
            ->where('stats.unassigned', 3)
            ->where('stats.refresh_ready', 1)
            ->where('stats.ready', 2)
            ->where('stats.blocked', 1)
            ->has('articles.data', 1)
            ->where('articles.data.0.title', 'Ready article')
            ->where('articles.data.0.latest_refresh_run.status', 'review_ready')
        );
    }
}
