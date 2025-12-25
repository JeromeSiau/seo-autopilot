<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\Keyword;
use App\Services\LLM\DTOs\ArticleOutline;
use App\Services\LLM\DTOs\GeneratedArticle;
use App\Services\LLM\DTOs\ResearchData;
use App\Services\LLM\LLMManager;
use Illuminate\Support\Facades\Log;

class ArticleGenerator
{
    private float $totalCost = 0;
    private array $llmsUsed = [];
    private int $startTime;

    public function __construct(
        private readonly LLMManager $llm,
    ) {}

    /**
     * Generate a complete article from a keyword.
     */
    public function generate(Keyword $keyword): GeneratedArticle
    {
        $this->startTime = time();
        $this->totalCost = 0;
        $this->llmsUsed = [];
        $this->llm->resetCosts();

        Log::info("Starting article generation for keyword: {$keyword->keyword}");

        // Step 1: Research & Analysis
        $research = $this->performResearch($keyword);

        // Step 2: Generate Outline
        $outline = $this->generateOutline($keyword, $research);

        // Step 3: Write Content (section by section)
        $content = $this->writeContent($outline, $research, $keyword->site);

        // Step 4: Polish & Generate Meta
        $polished = $this->polishArticle($content, $outline, $keyword);

        $this->totalCost = $this->llm->getTotalCost();
        $generationTime = time() - $this->startTime;

        Log::info("Article generation completed", [
            'keyword' => $keyword->keyword,
            'word_count' => str_word_count(strip_tags($polished['content'])),
            'total_cost' => $this->totalCost,
            'generation_time' => $generationTime,
        ]);

        return new GeneratedArticle(
            title: $outline->title,
            content: $polished['content'],
            metaTitle: $polished['meta_title'],
            metaDescription: $polished['meta_description'],
            images: [], // Images handled separately
            internalLinkSuggestions: $polished['internal_links'] ?? [],
            wordCount: str_word_count(strip_tags($polished['content'])),
            totalCost: $this->totalCost,
            generationTimeSeconds: $generationTime,
            llmsUsed: $this->llm->getCostBreakdown(),
        );
    }

    private function performResearch(Keyword $keyword): ResearchData
    {
        Log::info("Step 1: Performing research for '{$keyword->keyword}'");

        $prompt = <<<PROMPT
You are an SEO research analyst. Analyze the search landscape for the keyword: "{$keyword->keyword}"

Provide research data in JSON format with:
1. competitor_insights: Key insights from top-ranking content (what they cover well)
2. key_points_to_cover: Essential topics/points that must be included
3. content_gaps: Topics that competitors don't cover well (our opportunity)
4. suggested_angles: Unique angles we could take
5. suggested_word_count: Recommended article length based on competition

Language: {$keyword->site->language}

Respond with valid JSON only.
PROMPT;

        $response = $this->llm->executeStep('research', $prompt);
        $this->llmsUsed[] = $response->model;

        $data = $response->getJson() ?? [];
        $data['keyword'] = $keyword->keyword;

        return ResearchData::fromArray($data);
    }

    private function generateOutline(Keyword $keyword, ResearchData $research): ArticleOutline
    {
        Log::info("Step 2: Generating outline for '{$keyword->keyword}'");

        $researchContext = $research->toPromptContext();

        $prompt = <<<PROMPT
You are an expert SEO content strategist. Create a detailed article outline optimized for the keyword: "{$keyword->keyword}"

{$researchContext}

Generate a JSON outline with:
1. title: SEO-optimized H1 title (include keyword naturally)
2. meta_title: Under 60 characters, keyword at start
3. meta_description: 150-160 characters, compelling, includes keyword
4. estimated_word_count: Total target words
5. sections: Array of sections, each with:
   - heading: The H2/H3 heading text
   - level: 2 for H2, 3 for H3
   - target_word_count: Words for this section
   - key_points: Array of bullet points to cover

Create 4-7 main sections (H2s) with subsections (H3s) where appropriate.
Total word count should be around {$research->suggestedWordCount} words.
Language: {$keyword->site->language}

Respond with valid JSON only.
PROMPT;

        $response = $this->llm->executeStep('outline', $prompt);
        $this->llmsUsed[] = $response->model;

        $data = $response->getJson();

        if (!$data) {
            throw new \RuntimeException('Failed to parse outline JSON');
        }

        return ArticleOutline::fromArray($data);
    }

    private function writeContent(
        ArticleOutline $outline,
        ResearchData $research,
        \App\Models\Site $site
    ): string {
        Log::info("Step 3: Writing content sections");

        $brandContext = $site->toBrandVoiceContext();
        $content = "<h1>{$outline->title}</h1>\n\n";

        foreach ($outline->sections as $index => $section) {
            Log::debug("Writing section {$index}: {$section->heading}");

            $prompt = <<<PROMPT
You are an expert content writer. Write the following section of an article.

Article Title: {$outline->title}
Section Heading: {$section->heading}
Target Word Count: {$section->targetWordCount} words

Key Points to Cover:
PROMPT;
            foreach ($section->keyPoints as $point) {
                $prompt .= "- {$point}\n";
            }

            $prompt .= <<<PROMPT

Brand Voice Instructions:
{$brandContext}

Research Context:
{$research->toPromptContext()}

Guidelines:
- Write naturally, avoiding AI-sounding phrases
- Include specific examples and actionable advice
- Use transition sentences to flow smoothly
- Do NOT include the heading in your response (it will be added)
- Write ONLY the body content for this section
- Target exactly {$section->targetWordCount} words
PROMPT;

            $response = $this->llm->executeStep('write_section', $prompt);
            $this->llmsUsed[] = $response->model;

            $headingTag = "h" . $section->level;
            $content .= "<{$headingTag}>{$section->heading}</{$headingTag}>\n\n";
            $content .= $response->content . "\n\n";
        }

        return trim($content);
    }

    private function polishArticle(string $content, ArticleOutline $outline, Keyword $keyword): array
    {
        Log::info("Step 4: Polishing article");

        $prompt = <<<PROMPT
You are an SEO editor. Review and polish this article for the keyword: "{$keyword->keyword}"

Article:
{$content}

Provide a JSON response with:
1. content: The polished HTML content (fix any grammar, improve flow, ensure keyword usage)
2. meta_title: Final meta title (max 60 chars, keyword at start)
3. meta_description: Final meta description (150-160 chars)
4. internal_links: Array of suggested internal link anchor texts that could link to other articles
5. seo_score: A score from 0-100 assessing SEO optimization

Keep the HTML structure intact. Only make minor improvements to flow and readability.
PROMPT;

        $response = $this->llm->executeStep('polish', $prompt);
        $this->llmsUsed[] = $response->model;

        $data = $response->getJson();

        if (!$data || !isset($data['content'])) {
            // If JSON parsing fails, return original with outline meta
            return [
                'content' => $content,
                'meta_title' => $outline->metaTitle,
                'meta_description' => $outline->metaDescription,
                'internal_links' => [],
            ];
        }

        return $data;
    }

    /**
     * Generate article and save to database.
     */
    public function generateAndSave(Keyword $keyword): Article
    {
        $keyword->markAsGenerating();

        try {
            $generated = $this->generate($keyword);

            $article = Article::create([
                'site_id' => $keyword->site_id,
                'keyword_id' => $keyword->id,
                'title' => $generated->title,
                'content' => $generated->content,
                'meta_title' => $generated->metaTitle,
                'meta_description' => $generated->metaDescription,
                'images' => $generated->images,
                'status' => 'ready',
                'llm_used' => implode(', ', array_unique($this->llmsUsed)),
                'generation_cost' => $generated->totalCost,
                'word_count' => $generated->wordCount,
                'generation_time_seconds' => $generated->generationTimeSeconds,
            ]);

            $keyword->markAsCompleted();

            return $article;
        } catch (\Exception $e) {
            Log::error("Article generation failed", [
                'keyword' => $keyword->keyword,
                'error' => $e->getMessage(),
            ]);

            $keyword->update(['status' => 'pending']); // Reset for retry

            throw $e;
        }
    }
}
