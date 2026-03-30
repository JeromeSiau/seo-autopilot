<?php

namespace App\Services\Refresh;

use App\Models\Article;
use App\Models\ArticleRefreshRun;
use App\Models\RefreshRecommendation;
use App\Services\Analytics\BusinessAttributionService;
use App\Services\Content\ArticleScoringService;
use App\Services\Webhooks\WebhookDispatcher;
use Illuminate\Support\Str;

class RefreshExecutionService
{
    public function __construct(
        private readonly ArticleScoringService $articleScoring,
        private readonly BusinessAttributionService $businessAttribution,
        private readonly WebhookDispatcher $webhooks,
    ) {}

    public function execute(RefreshRecommendation $recommendation): ArticleRefreshRun
    {
        $article = $recommendation->article->loadMissing([
            'site.brandAssets',
            'site.brandRules',
            'site.pages',
            'keyword',
            'citations',
            'agentEvents',
            'score',
            'analytics',
        ]);

        $actions = collect($recommendation->recommended_actions ?? []);
        $linkedPages = $article->site->pages->take(2)->pluck('title')->filter()->values();
        $summary = $this->buildSummary($article, $recommendation, $actions, $linkedPages);
        $draftMetaTitle = $this->buildMetaTitle($article);
        $draftMetaDescription = $this->buildMetaDescription($article, $recommendation);
        $draftContent = $this->buildDraftContent($article, $recommendation, $summary, $linkedPages);
        $diff = $this->buildDiff($article, $draftMetaTitle, $draftMetaDescription, $draftContent);
        $businessCase = $this->buildBusinessCase($article, $recommendation);

        $draftArticle = $this->buildDraftArticle($article, $draftMetaTitle, $draftMetaDescription, $draftContent);
        $newScore = $this->articleScoring->scorePayload($draftArticle);

        $run = $article->refreshRuns()->create([
            'refresh_recommendation_id' => $recommendation->id,
            'old_score_snapshot' => $article->score?->only([
                'readiness_score',
                'brand_fit_score',
                'seo_score',
                'citation_score',
                'internal_link_score',
                'fact_confidence_score',
                'warnings',
                'checklist',
            ]) ?? [],
            'new_score_snapshot' => $newScore,
            'status' => 'drafted',
            'summary' => $summary,
            'metadata' => [
                'draft_title' => $article->title,
                'draft_meta_title' => $draftMetaTitle,
                'draft_meta_description' => $draftMetaDescription,
                'draft_content' => $draftContent,
                'diff' => $diff,
                'focus_sections' => $diff['sections_added'],
                'business_case' => $businessCase,
            ],
        ]);

        $recommendation->update([
            'status' => RefreshRecommendation::STATUS_EXECUTED,
            'executed_at' => now(),
        ]);

        $article->editorialComments()->create([
            'user_id' => $article->site->team->owner_id,
            'body' => "Refresh draft generated.\n\n{$summary}",
            'resolved_at' => null,
        ]);

        $this->webhooks->dispatch($article->site->team, 'refresh.executed', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'refresh_recommendation_id' => $recommendation->id,
            'refresh_run_id' => $run->id,
            'status' => $run->status,
        ]);

        return $run->fresh();
    }

    public function applyDraftToReview(RefreshRecommendation $recommendation): Article
    {
        $article = $recommendation->article->loadMissing([
            'site.brandAssets',
            'site.brandRules',
            'keyword',
            'citations',
            'agentEvents',
            'score',
            'refreshRuns',
            'analytics',
        ]);

        /** @var ArticleRefreshRun|null $run */
        $run = $recommendation->runs()->latest('created_at')->first();
        $draftContent = (string) data_get($run?->metadata, 'draft_content', '');

        if (!$run || blank($draftContent)) {
            throw new \RuntimeException('No refresh draft exists for this recommendation.');
        }

        $article->forceFill([
            'content' => $draftContent,
            'meta_title' => data_get($run->metadata, 'draft_meta_title', $article->meta_title),
            'meta_description' => data_get($run->metadata, 'draft_meta_description', $article->meta_description),
            'status' => Article::STATUS_REVIEW,
        ])->save();

        $this->articleScoring->scoreAndSave($article->fresh([
            'site.brandAssets',
            'site.brandRules',
            'keyword',
            'citations',
            'agentEvents',
        ]));

        $run->update([
            'status' => 'review_ready',
            'metadata' => array_merge($run->metadata ?? [], [
                'applied_at' => now()->toIso8601String(),
            ]),
        ]);

        $article->editorialComments()->create([
            'user_id' => $article->site->team->owner_id,
            'body' => "Refresh draft applied to article and moved back to review.\n\n{$run->summary}",
            'resolved_at' => null,
        ]);

        $this->webhooks->dispatch($article->site->team, 'refresh.ready_for_review', [
            'team_id' => $article->site->team_id,
            'site_id' => $article->site_id,
            'article_id' => $article->id,
            'refresh_recommendation_id' => $recommendation->id,
            'refresh_run_id' => $run->id,
            'status' => $run->status,
        ]);

        return $article->fresh();
    }

    private function buildDraftArticle(Article $article, string $metaTitle, string $metaDescription, string $content): Article
    {
        $draft = $article->replicate();
        $draft->content = $content;
        $draft->meta_title = $metaTitle;
        $draft->meta_description = $metaDescription;
        $draft->word_count = str_word_count(strip_tags($content));
        $draft->setRelation('site', $article->site);
        $draft->setRelation('keyword', $article->keyword);
        $draft->setRelation('citations', $article->citations);
        $draft->setRelation('agentEvents', $article->agentEvents);

        return $draft;
    }

    private function buildSummary(Article $article, RefreshRecommendation $recommendation, $actions, $linkedPages): string
    {
        $lines = [
            "Refresh trigger: {$recommendation->trigger_type}.",
            $recommendation->reason,
        ];

        $aiVisibility = data_get($recommendation->metrics_snapshot, 'ai_visibility');
        if (is_array($aiVisibility)) {
            if (isset($aiVisibility['previous_avg'], $aiVisibility['recent_avg'])) {
                $lines[] = "AI visibility moved from {$aiVisibility['previous_avg']} to {$aiVisibility['recent_avg']}.";
            }

            if (filled(data_get($aiVisibility, 'weakest_engine'))) {
                $lines[] = 'Weakest engine right now: ' . Str::headline((string) data_get($aiVisibility, 'weakest_engine')) . '.';
            }

            $promptTopics = collect(data_get($aiVisibility, 'matching_prompts', []))->filter()->take(3);
            if ($promptTopics->isNotEmpty()) {
                $lines[] = 'Prompts to strengthen explicitly: ' . $promptTopics->implode(', ') . '.';
            }

            $competitorDomains = collect(data_get($aiVisibility, 'competitor_domains', []))->filter()->take(3);
            if ($competitorDomains->isNotEmpty()) {
                $lines[] = 'Competitor pressure from: ' . $competitorDomains->implode(', ') . '.';
            }
        }

        foreach ($actions as $action) {
            $lines[] = "- {$action}";
        }

        if ($linkedPages->isNotEmpty()) {
            $lines[] = 'Suggested internal links: ' . $linkedPages->implode(', ') . '.';
        }

        return implode("\n", $lines);
    }

    private function buildMetaTitle(Article $article): string
    {
        $base = $article->meta_title ?: $article->title;
        $withYear = preg_match('/\b20\d{2}\b/', $base) ? $base : "{$base} ({now()->year} Update)";

        return Str::limit($withYear, 60, '');
    }

    private function buildMetaDescription(Article $article, RefreshRecommendation $recommendation): string
    {
        $description = $article->meta_description ?: strip_tags((string) $article->content);
        $suffix = ' Updated with fresher guidance, proof points, and clearer next steps.';

        return Str::limit(trim($description) . $suffix, 160, '');
    }

    private function buildDraftContent(Article $article, RefreshRecommendation $recommendation, string $summary, $linkedPages): string
    {
        $additions = [
            '<h2>What changed in this refresh</h2>',
            '<ul>' . collect(explode("\n", $summary))
                ->filter(fn (string $line) => Str::startsWith($line, '- '))
                ->map(fn (string $line) => '<li>' . e(Str::after($line, '- ')) . '</li>')
                ->implode('') . '</ul>',
        ];

        $aiMetrics = data_get($recommendation->metrics_snapshot, 'ai_visibility');

        if (is_array($aiMetrics)) {
            $promptTopics = collect(data_get($aiMetrics, 'matching_prompts', []))
                ->filter()
                ->take(4);

            if ($promptTopics->isNotEmpty()) {
                $additions[] = '<h2>Questions this update should answer more explicitly</h2>';
                $additions[] = '<ul>' . $promptTopics
                    ->map(fn (string $topic) => '<li>' . e($topic) . '</li>')
                    ->implode('') . '</ul>';
            }

            $competitorDomains = collect(data_get($aiMetrics, 'competitor_domains', []))
                ->filter()
                ->take(3);

            if ($competitorDomains->isNotEmpty()) {
                $additions[] = '<h2>Competitive gaps to close</h2>';
                $additions[] = '<p>Clarify how your approach differs from alternatives surfaced by AI answers such as ' . e($competitorDomains->implode(', ')) . '.</p>';
            }
        }

        if ($linkedPages->isNotEmpty()) {
            $additions[] = '<h2>Related pages to connect</h2>';
            $additions[] = '<p>Consider linking this article to ' . e($linkedPages->implode(', ')) . ' to reinforce the cluster.</p>';
        }

        return trim((string) $article->content) . "\n\n" . implode("\n", $additions);
    }

    private function buildDiff(Article $article, string $draftMetaTitle, string $draftMetaDescription, string $draftContent): array
    {
        $oldHeadings = $this->extractHeadings((string) $article->content);
        $newHeadings = $this->extractHeadings($draftContent);
        $sectionsAdded = array_values(array_slice(array_diff($newHeadings, $oldHeadings), 0, 6));

        return [
            'old_meta_title' => $article->meta_title ?: $article->title,
            'new_meta_title' => $draftMetaTitle,
            'old_meta_description' => $article->meta_description,
            'new_meta_description' => $draftMetaDescription,
            'meta_title_changed' => ($article->meta_title ?: $article->title) !== $draftMetaTitle,
            'meta_description_changed' => (string) $article->meta_description !== $draftMetaDescription,
            'old_word_count' => $article->word_count ?? str_word_count(strip_tags((string) $article->content)),
            'new_word_count' => str_word_count(strip_tags($draftContent)),
            'word_delta' => str_word_count(strip_tags($draftContent)) - (int) ($article->word_count ?? str_word_count(strip_tags((string) $article->content))),
            'sections_added' => $sectionsAdded,
        ];
    }

    private function buildBusinessCase(Article $article, RefreshRecommendation $recommendation): array
    {
        $summary = $this->businessAttribution->summarizeArticle($article);

        return [
            'traffic_value' => data_get($summary, 'totals.traffic_value'),
            'estimated_conversions' => data_get($summary, 'totals.estimated_conversions'),
            'conversion_source' => data_get($summary, 'totals.conversion_source'),
            'roi' => data_get($summary, 'roi'),
            'traffic_value_delta' => data_get($summary, 'deltas.traffic_value.absolute'),
            'conversion_delta' => data_get($summary, 'deltas.estimated_conversions.absolute'),
            'click_delta' => data_get($summary, 'deltas.clicks.absolute'),
            'session_delta' => data_get($summary, 'deltas.sessions.absolute'),
            'trigger_type' => $recommendation->trigger_type,
        ];
    }

    private function extractHeadings(string $html): array
    {
        if (blank($html)) {
            return [];
        }

        preg_match_all('/<h[2-4][^>]*>(.*?)<\/h[2-4]>/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $heading) => trim(strip_tags($heading)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
