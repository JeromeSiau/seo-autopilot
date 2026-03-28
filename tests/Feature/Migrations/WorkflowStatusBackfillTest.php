<?php

namespace Tests\Feature\Migrations;

use App\Models\Article;
use App\Models\Keyword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTeams;
use Tests\TestCase;

class WorkflowStatusBackfillTest extends TestCase
{
    use CreatesTeams;
    use RefreshDatabase;

    public function test_status_backfill_migrates_legacy_values(): void
    {
        $user = $this->createUserWithTeam();
        $site = $this->createSiteForUser($user);

        DB::statement('PRAGMA ignore_check_constraints = 1');

        $keyword = Keyword::create([
            'site_id' => $site->id,
            'keyword' => 'legacy keyword',
            'status' => 'processing',
            'source' => 'manual',
        ]);

        $article = Article::create([
            'site_id' => $site->id,
            'keyword_id' => $keyword->id,
            'title' => 'Legacy article',
            'slug' => 'legacy-article',
            'status' => 'ready',
        ]);

        DB::statement('PRAGMA ignore_check_constraints = 0');

        $migration = require database_path('migrations/2026_03_28_000001_backfill_workflow_statuses.php');
        $migration->up();

        $this->assertSame(Keyword::STATUS_QUEUED, $keyword->fresh()->status);
        $this->assertSame(Article::STATUS_REVIEW, $article->fresh()->status);
    }
}
