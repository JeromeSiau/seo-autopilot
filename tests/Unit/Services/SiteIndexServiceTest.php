<?php

namespace Tests\Unit\Services;

use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Services\Crawler\SiteIndexService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;

class SiteIndexServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_index_path_returns_correct_path(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create(['team_id' => $team->id]);

        $service = new SiteIndexService();
        $path = $service->getIndexPath($site);

        $this->assertStringContainsString("site_{$site->id}.sqlite", $path);
        $this->assertStringContainsString('storage/indexes', $path);
    }

    public function test_has_index_returns_false_when_no_index(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create(['team_id' => $team->id]);

        $service = new SiteIndexService();

        $this->assertFalse($service->hasIndex($site));
    }

    public function test_index_site_runs_node_agent(): void
    {
        Process::fake([
            '*' => Process::result(
                output: json_encode(['success' => true, 'pages_indexed' => 10]),
            ),
        ]);

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        $site = Site::factory()->create(['team_id' => $team->id, 'domain' => 'example.com']);

        $service = new SiteIndexService();
        $result = $service->indexSite($site);

        $this->assertEquals(10, $result['pages_indexed']);
        $this->assertTrue($result['success']);

        Process::assertRan(function ($process) use ($site) {
            $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;
            return str_contains($command, 'site-indexer') &&
                str_contains($command, '--siteId') &&
                str_contains($command, (string) $site->id);
        });
    }
}
