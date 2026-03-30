<?php

namespace App\Services\AiVisibility;

use App\Models\AiVisibilityCheck;
use App\Models\Article;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AiVisibilityRecommendationService
{
    public function buildRecommendations(Site $site, Collection $latestChecks): array
    {
        $site->loadMissing(['articles.keyword', 'brandAssets']);

        $previousChecks = AiVisibilityCheck::query()
            ->with('prompt')
            ->where('site_id', $site->id)
            ->whereIn('ai_prompt_id', $latestChecks->pluck('ai_prompt_id')->filter()->unique())
            ->orderByDesc('checked_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (AiVisibilityCheck $check) => "{$check->ai_prompt_id}:{$check->engine}")
            ->mapWithKeys(fn (Collection $checks, string $key) => [$key => $checks->skip(1)->first()])
            ->filter();

        return $latestChecks
            ->groupBy('ai_prompt_id')
            ->map(function (Collection $checks) use ($site, $previousChecks) {
                /** @var AiVisibilityCheck $primaryCheck */
                $primaryCheck = $checks
                    ->sortBy(function (AiVisibilityCheck $check) use ($previousChecks) {
                        $previousScore = $previousChecks->get("{$check->ai_prompt_id}:{$check->engine}")?->visibility_score;
                        $delta = $previousScore !== null
                            ? (float) $check->visibility_score - (float) $previousScore
                            : 0;

                        return ((int) $check->visibility_score * 1000) + $delta;
                    })
                    ->first();

                $matchingArticle = $this->findMatchingArticle($site, (string) ($primaryCheck->prompt?->topic ?: $primaryCheck->prompt?->prompt));
                $previousScore = $previousChecks->get("{$primaryCheck->ai_prompt_id}:{$primaryCheck->engine}")?->visibility_score;
                $delta = $previousScore !== null
                    ? round((float) $primaryCheck->visibility_score - (float) $previousScore, 1)
                    : 0.0;
                $relatedDomains = $checks
                    ->flatMap(fn (AiVisibilityCheck $check) => $check->mentions
                        ->where('is_our_brand', false)
                        ->pluck('domain'))
                    ->filter()
                    ->unique()
                    ->values()
                    ->take(3)
                    ->all();
                $severity = $this->severityForCheck($primaryCheck, $delta, $matchingArticle);
                $engineLabel = $this->engineLabel($primaryCheck->engine);

                if (!$matchingArticle) {
                    return [
                        'type' => 'create_article',
                        'title' => "Create dedicated coverage for “{$primaryCheck->prompt?->topic}”",
                        'reason' => $delta <= -10
                            ? "Coverage regressed on {$engineLabel} and there is still no published article mapped to this prompt cluster."
                            : "This prompt cluster has no clearly mapped published article yet, which leaves AI answers to competitors.",
                        'prompt_id' => $primaryCheck->ai_prompt_id,
                        'article_id' => null,
                        'article_title' => null,
                        'severity' => $severity,
                        'engine' => $primaryCheck->engine,
                        'topic' => $primaryCheck->prompt?->topic,
                        'intent' => $primaryCheck->prompt?->intent,
                        'visibility_score' => (int) $primaryCheck->visibility_score,
                        'previous_visibility_score' => $previousScore !== null ? (int) $previousScore : null,
                        'visibility_delta' => $delta,
                        'related_domains' => $relatedDomains,
                        'action_label' => 'Plan article',
                    ];
                }

                if (($primaryCheck->visibility_score ?? 0) < 52 || $delta <= -10) {
                    return [
                        'type' => 'refresh_article',
                        'title' => "Refresh “{$matchingArticle->title}”",
                        'reason' => $delta <= -10
                            ? "This topic lost {$this->formatDelta($delta)} points on {$engineLabel}; refresh the article before the drop compounds."
                            : "Coverage exists, but the current AI visibility score is still weak and needs stronger, more explicit treatment.",
                        'prompt_id' => $primaryCheck->ai_prompt_id,
                        'article_id' => $matchingArticle->id,
                        'article_title' => $matchingArticle->title,
                        'severity' => $severity,
                        'engine' => $primaryCheck->engine,
                        'topic' => $primaryCheck->prompt?->topic,
                        'intent' => $primaryCheck->prompt?->intent,
                        'visibility_score' => (int) $primaryCheck->visibility_score,
                        'previous_visibility_score' => $previousScore !== null ? (int) $previousScore : null,
                        'visibility_delta' => $delta,
                        'related_domains' => $relatedDomains,
                        'action_label' => 'Open refresh planner',
                    ];
                }

                return [
                    'type' => 'add_citations',
                    'title' => "Strengthen sources for “{$matchingArticle->title}”",
                    'reason' => !empty($relatedDomains)
                        ? 'Competitors are still present in AI answers. Add stronger citations, proof points, and more explicit source coverage.'
                        : 'Coverage exists, but source support is still thinner than the strongest results.',
                    'prompt_id' => $primaryCheck->ai_prompt_id,
                    'article_id' => $matchingArticle->id,
                    'article_title' => $matchingArticle->title,
                    'severity' => $severity,
                    'engine' => $primaryCheck->engine,
                    'topic' => $primaryCheck->prompt?->topic,
                    'intent' => $primaryCheck->prompt?->intent,
                    'visibility_score' => (int) $primaryCheck->visibility_score,
                    'previous_visibility_score' => $previousScore !== null ? (int) $previousScore : null,
                    'visibility_delta' => $delta,
                    'related_domains' => $relatedDomains,
                    'action_label' => 'Review citations',
                ];
            })
            ->sortBy(function (array $recommendation) {
                $severityWeight = match ($recommendation['severity']) {
                    'high' => 3,
                    'medium' => 2,
                    default => 1,
                };
                $deltaWeight = max(0, (float) -($recommendation['visibility_delta'] ?? 0));

                return -(($severityWeight * 1000) + ((100 - (int) $recommendation['visibility_score']) * 10) + $deltaWeight);
            })
            ->take(6)
            ->values()
            ->all();
    }

    private function findMatchingArticle(Site $site, string $topic): ?Article
    {
        $needle = Str::lower($topic);

        return $site->articles
            ->where('status', Article::STATUS_PUBLISHED)
            ->first(function (Article $article) use ($needle) {
                return Str::contains(Str::lower($article->title), $needle)
                    || Str::contains(Str::lower($article->keyword?->keyword ?? ''), $needle)
                    || Str::contains(Str::lower(strip_tags((string) $article->content)), $needle);
            });
    }

    private function severityForCheck(AiVisibilityCheck $check, float $delta, ?Article $article): string
    {
        if (($check->visibility_score ?? 0) < 40 || $delta <= -15 || $article === null) {
            return 'high';
        }

        if (($check->visibility_score ?? 0) < 60 || $delta <= -6) {
            return 'medium';
        }

        return 'low';
    }

    private function engineLabel(string $engine): string
    {
        return match ($engine) {
            AiVisibilityCheck::ENGINE_AI_OVERVIEWS => 'AI Overviews',
            AiVisibilityCheck::ENGINE_CHATGPT => 'ChatGPT',
            AiVisibilityCheck::ENGINE_PERPLEXITY => 'Perplexity',
            AiVisibilityCheck::ENGINE_GEMINI => 'Gemini',
            default => Str::headline($engine),
        };
    }

    private function formatDelta(float $delta): string
    {
        return number_format(abs($delta), 1);
    }
}
