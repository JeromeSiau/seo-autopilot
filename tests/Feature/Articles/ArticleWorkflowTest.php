<?php

namespace Tests\Feature\Articles;

use App\Jobs\RefreshSiteArticleScoresJob;
use App\Models\ApprovalRequest;
use App\Models\Article;
use App\Models\ArticleAssignment;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class ArticleWorkflowTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_team_can_comment_assign_request_approval_and_target_user_can_approve(): void
    {
        Queue::fake();

        $owner = $this->createUserWithTeam();
        $site = $this->createSiteForUser($owner);
        $article = Article::factory()->create([
            'site_id' => $site->id,
            'status' => Article::STATUS_REVIEW,
        ]);

        $reviewer = User::factory()->create();
        $reviewer->teams()->attach($owner->currentTeam->id, ['role' => 'member']);
        $reviewer->update(['current_team_id' => $owner->currentTeam->id]);

        $this->actingAs($owner)
            ->post(route('articles.assignments.store', $article), [
                'user_id' => $reviewer->id,
                'role' => ArticleAssignment::ROLE_REVIEWER,
            ])
            ->assertRedirect();

        $this->actingAs($reviewer)
            ->post(route('articles.comments.store', $article), [
                'body' => 'Please verify the claims in the introduction.',
            ])
            ->assertRedirect();

        $this->actingAs($owner)
            ->post(route('articles.approval-requests.store', $article), [
                'requested_to' => $reviewer->id,
                'decision_note' => 'Please approve once the intro is accurate.',
            ])
            ->assertRedirect();

        $approvalRequest = ApprovalRequest::query()->firstOrFail();

        $this->actingAs($reviewer)
            ->post(route('articles.approval-requests.approve', [
                'article' => $article,
                'approvalRequest' => $approvalRequest,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('editorial_comments', [
            'article_id' => $article->id,
            'user_id' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('article_assignments', [
            'article_id' => $article->id,
            'user_id' => $reviewer->id,
            'role' => ArticleAssignment::ROLE_REVIEWER,
        ]);
        $this->assertDatabaseHas('approval_requests', [
            'id' => $approvalRequest->id,
            'status' => ApprovalRequest::STATUS_APPROVED,
            'requested_to' => $reviewer->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reviewer->id,
            'type' => Notification::TYPE_ASSIGNMENT,
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reviewer->id,
            'type' => Notification::TYPE_APPROVAL_REQUESTED,
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => Notification::TYPE_EDITORIAL_COMMENT,
            'site_id' => $site->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $owner->id,
            'type' => Notification::TYPE_APPROVAL_DECIDED,
            'site_id' => $site->id,
        ]);
        $this->assertSame(Article::STATUS_APPROVED, $article->fresh()->status);

        Queue::assertPushed(RefreshSiteArticleScoresJob::class, 1);
    }

    public function test_member_cannot_manually_approve_publish_or_assign_article(): void
    {
        $owner = $this->createUserWithTeam();
        $site = $this->createSiteForUser($owner);
        $article = Article::factory()->create([
            'site_id' => $site->id,
            'status' => Article::STATUS_REVIEW,
        ]);

        $member = User::factory()->create();
        $member->teams()->attach($owner->currentTeam->id, ['role' => 'member']);
        $member->update(['current_team_id' => $owner->currentTeam->id]);

        $this->actingAs($member)
            ->post(route('articles.approve', $article))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('articles.assignments.store', $article), [
                'user_id' => $member->id,
                'role' => ArticleAssignment::ROLE_WRITER,
            ])
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('articles.publish', $article), [
                'integration_id' => 999999,
            ])
            ->assertForbidden();
    }
}
