<?php

namespace Tests\Unit\Models;

use App\Models\BrandAsset;
use App\Models\BrandRule;
use App\Models\Site;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteBrandContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_brand_context_includes_site_fields_rules_and_assets(): void
    {
        $site = Site::factory()->create([
            'business_description' => 'Acme sells industrial software.',
            'target_audience' => 'Operations teams',
            'tone' => 'expert',
            'writing_style' => 'Direct and practical',
            'vocabulary' => [
                'use' => ['throughput', 'downtime'],
                'avoid' => ['magic', 'guaranteed'],
            ],
            'brand_examples' => ['We prefer evidence-backed explanations.'],
        ]);

        $site->brandRules()->create([
            'category' => BrandRule::CATEGORY_MUST_INCLUDE,
            'label' => 'Use proof',
            'value' => 'Include operational proof points when possible.',
            'priority' => 90,
        ]);

        $site->brandAssets()->create([
            'type' => BrandAsset::TYPE_CASE_STUDY,
            'title' => 'Factory rollout',
            'content' => 'A rollout reduced downtime by 18 percent across three plants.',
            'priority' => 80,
        ]);

        $context = $site->fresh()->toBrandVoiceContext();

        $this->assertStringContainsString('Business: Acme sells industrial software.', $context);
        $this->assertStringContainsString('Target Audience: Operations teams', $context);
        $this->assertStringContainsString('Writing Style: Direct and practical', $context);
        $this->assertStringContainsString('Tone: expert', $context);
        $this->assertStringContainsString('Use proof', $context);
        $this->assertStringContainsString('Factory rollout', $context);
    }
}
