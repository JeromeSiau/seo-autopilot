<?php

namespace Tests\Unit\Models;

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_has_fillable_attributes(): void
    {
        $plan = Plan::create([
            'slug' => 'test',
            'name' => 'Test Plan',
            'price' => 99,
            'articles_per_month' => 30,
            'sites_limit' => 3,
            'features' => ['Feature 1', 'Feature 2'],
        ]);

        $this->assertEquals('test', $plan->slug);
        $this->assertEquals(99, $plan->price);
        $this->assertIsArray($plan->features);
    }

    public function test_is_unlimited_sites_returns_true_for_negative_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => -1]);
        $this->assertTrue($plan->isUnlimitedSites());
    }

    public function test_is_unlimited_sites_returns_false_for_positive_limit(): void
    {
        $plan = Plan::factory()->create(['sites_limit' => 3]);
        $this->assertFalse($plan->isUnlimitedSites());
    }

    public function test_active_scope_returns_only_active_plans(): void
    {
        Plan::factory()->create(['is_active' => true, 'slug' => 'active']);
        Plan::factory()->create(['is_active' => false, 'slug' => 'inactive']);

        $activePlans = Plan::active()->get();

        $this->assertCount(1, $activePlans);
        $this->assertEquals('active', $activePlans->first()->slug);
    }
}
