<?php

namespace App\Services\ContentPlan;

use App\Services\LLM\LLMManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DuplicateCheckerService
{
    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    public function filterDuplicates(Collection $keywords, Collection $sitePages): Collection
    {
        if ($sitePages->isEmpty() || $keywords->isEmpty()) {
            return $keywords;
        }

        $existingTitles = $sitePages
            ->filter(fn($title) => !empty($title))
            ->values()
            ->take(100)
            ->toArray();

        if (empty($existingTitles)) {
            return $keywords;
        }

        $keywordList = $keywords->pluck('keyword')->take(50)->toArray();
        $prompt = $this->buildPrompt($keywordList, $existingTitles);

        try {
            $response = $this->llm->completeJson('openai', $prompt, [], [
                'model' => 'gpt-4o-mini',
                'temperature' => 0.1,
            ]);

            $results = $response->getJson() ?? [];

            Log::info("Duplicate check completed", [
                'keywords_checked' => count($keywordList),
                'results' => $results,
            ]);

            return $keywords->filter(function ($kw) use ($results) {
                $keyword = $kw['keyword'] ?? '';
                $status = $results[$keyword] ?? 'new';
                return $status !== 'covered';
            });
        } catch (\Exception $e) {
            Log::warning("Duplicate check failed, returning all keywords", ['error' => $e->getMessage()]);
            return $keywords;
        }
    }

    private function buildPrompt(array $keywords, array $existingTitles): string
    {
        $keywordList = implode("\n- ", $keywords);
        $titleList = implode("\n- ", $existingTitles);

        return <<<PROMPT
Tu es un expert SEO. Analyse ces mots-clés candidats et détermine s'ils sont déjà couverts par les articles existants.

**Mots-clés candidats:**
- {$keywordList}

**Articles existants sur le site:**
- {$titleList}

Pour chaque mot-clé, indique:
- "new" = sujet pas encore couvert, bon candidat pour un nouvel article
- "covered" = un article existant couvre déjà ce sujet (même si le titre est différent)
- "partial" = partiellement couvert, pourrait être un angle complémentaire

Sois strict: si un article existant traite du même sujet principal, marque "covered".

Réponds UNIQUEMENT avec un objet JSON valide, format: {"mot-clé": "status", ...}
PROMPT;
    }
}
