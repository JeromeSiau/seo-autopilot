<?php

namespace App\Services\Content;

use App\Models\AgentEvent;
use App\Models\Article;
use App\Models\ArticleScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ArticleScoringService
{
    public function scoreAndSave(Article $article): ArticleScore
    {
        $payload = $this->scorePayload($article);

        return $article->score()->updateOrCreate([], $payload);
    }

    public function scorePayload(Article $article): array
    {
        $article->loadMissing([
            'site.brandAssets',
            'site.brandRules',
            'keyword',
            'citations',
            'agentEvents',
        ]);

        $seoScore = $this->seoScore($article);
        $brandFitScore = $this->brandFitScore($article);
        $citationScore = $this->citationScore($article);
        $internalLinkScore = $this->internalLinkScore($article);
        $factConfidenceScore = $this->factConfidenceScore($article, $citationScore);
        $readinessScore = (int) round(collect([
            $seoScore,
            $brandFitScore,
            $citationScore,
            $internalLinkScore,
            $factConfidenceScore,
        ])->avg());

        $payload = [
            'readiness_score' => $readinessScore,
            'brand_fit_score' => $brandFitScore,
            'seo_score' => $seoScore,
            'citation_score' => $citationScore,
            'internal_link_score' => $internalLinkScore,
            'fact_confidence_score' => $factConfidenceScore,
            'warnings' => $this->warnings($article),
            'checklist' => $this->checklist($article),
        ];

        return $payload;
    }

    private function seoScore(Article $article): int
    {
        $score = 0;
        $keyword = Str::lower($article->keyword?->keyword ?? '');
        $plain = Str::lower(strip_tags((string) $article->content));
        $metaTitle = $article->meta_title ?? '';
        $metaDescription = $article->meta_description ?? '';

        if ($article->title) {
            $score += 10;
        }

        if ($metaTitle !== '') {
            $score += 10;
        }

        if ($metaDescription !== '') {
            $score += 10;
        }

        if ($article->word_count >= 1200) {
            $score += 20;
        } elseif ($article->word_count >= 800) {
            $score += 15;
        } elseif ($article->word_count >= 500) {
            $score += 10;
        }

        if ($keyword !== '' && Str::contains(Str::lower($article->title), $keyword)) {
            $score += 15;
        }

        if ($keyword !== '' && Str::contains($plain, $keyword)) {
            $score += 15;
        }

        if (preg_match('/<h2[\s>]/i', (string) $article->content)) {
            $score += 10;
        }

        if (Str::length($metaTitle) > 0 && Str::length($metaTitle) <= 60) {
            $score += 5;
        }

        if (Str::length($metaDescription) >= 120 && Str::length($metaDescription) <= 160) {
            $score += 5;
        }

        return min(100, $score);
    }

    private function brandFitScore(Article $article): int
    {
        $site = $article->site;
        $score = 55;
        $plain = Str::lower(strip_tags((string) $article->content));

        if ($site->tone || $site->writing_style) {
            $score += 10;
        }

        $useWords = collect($site->vocabulary['use'] ?? []);
        if ($useWords->isNotEmpty()) {
            $matches = $useWords->filter(fn (string $word) => Str::contains($plain, Str::lower($word)))->count();
            $score += min(15, $matches * 5);
        } else {
            $score += 10;
        }

        $avoidWords = collect($site->vocabulary['avoid'] ?? []);
        if ($avoidWords->isNotEmpty()) {
            $violations = $avoidWords->filter(fn (string $word) => Str::contains($plain, Str::lower($word)))->count();
            $score += max(0, 15 - ($violations * 7));
        } else {
            $score += 10;
        }

        if ($site->brandRules->isNotEmpty()) {
            $score += 10;
        }

        if ($article->citations->contains(fn ($citation) => $citation->source_type === 'brand')) {
            $score += 15;
        }

        return min(100, $score);
    }

    private function citationScore(Article $article): int
    {
        $count = $article->citations->count();
        $types = $article->citations->pluck('source_type')->unique()->count();

        $base = match (true) {
            $count >= 4 => 90,
            $count === 3 => 80,
            $count === 2 => 65,
            $count === 1 => 45,
            default => 15,
        };

        if ($types >= 2) {
            $base += 10;
        }

        return min(100, $base);
    }

    private function internalLinkScore(Article $article): int
    {
        $content = (string) $article->content;
        $site = $article->site;
        $count = 0;

        preg_match_all('/<a[^>]+href="([^"]+)"/i', $content, $matches);
        $hrefs = $matches[1] ?? [];

        foreach ($hrefs as $href) {
            if (str_starts_with($href, '/')) {
                $count++;
                continue;
            }

            if ($site->public_url && str_starts_with($href, $site->public_url)) {
                $count++;
                continue;
            }

            if (str_contains($href, $site->domain)) {
                $count++;
            }
        }

        return match (true) {
            $count >= 3 => 100,
            $count === 2 => 80,
            $count === 1 => 60,
            default => 20,
        };
    }

    private function factConfidenceScore(Article $article, int $citationScore): int
    {
        $factCheckerEvents = $article->agentEvents
            ->where('agent_type', AgentEvent::TYPE_FACT_CHECKER);

        if ($factCheckerEvents->where('event_type', AgentEvent::EVENT_ERROR)->isNotEmpty()) {
            return 30;
        }

        if ($factCheckerEvents->where('event_type', AgentEvent::EVENT_COMPLETED)->isNotEmpty()) {
            return max(70, $citationScore);
        }

        return min(100, 40 + (int) round($citationScore * 0.6));
    }

    private function warnings(Article $article): array
    {
        $warnings = [];

        if (!$article->meta_title || Str::length($article->meta_title) > 60) {
            $warnings[] = 'Meta title should be present and stay within 60 characters.';
        }

        if (!$article->meta_description || Str::length($article->meta_description) < 120 || Str::length($article->meta_description) > 160) {
            $warnings[] = 'Meta description should target the 120-160 character range.';
        }

        if ($article->citations->isEmpty()) {
            $warnings[] = 'No reference sources are attached to this article yet.';
        }

        if ($this->internalLinkScore($article) < 60) {
            $warnings[] = 'Internal linking is still weak.';
        }

        if ($article->agentEvents->where('agent_type', AgentEvent::TYPE_FACT_CHECKER)->where('event_type', AgentEvent::EVENT_ERROR)->isNotEmpty()) {
            $warnings[] = 'Fact checking failed and should be reviewed manually.';
        }

        return $warnings;
    }

    private function checklist(Article $article): array
    {
        return [
            [
                'label' => 'Meta title is present and within length',
                'done' => filled($article->meta_title) && Str::length($article->meta_title) <= 60,
            ],
            [
                'label' => 'Meta description is present and within target range',
                'done' => filled($article->meta_description)
                    && Str::length($article->meta_description) >= 120
                    && Str::length($article->meta_description) <= 160,
            ],
            [
                'label' => 'Article includes at least one H2 section',
                'done' => preg_match('/<h2[\s>]/i', (string) $article->content) === 1,
            ],
            [
                'label' => 'Reference sources are attached',
                'done' => $article->citations->isNotEmpty(),
            ],
            [
                'label' => 'Internal links were added',
                'done' => $this->internalLinkScore($article) >= 60,
            ],
            [
                'label' => 'Fact checking completed without error',
                'done' => !$article->agentEvents
                    ->where('agent_type', AgentEvent::TYPE_FACT_CHECKER)
                    ->where('event_type', AgentEvent::EVENT_ERROR)
                    ->isNotEmpty(),
            ],
        ];
    }
}
