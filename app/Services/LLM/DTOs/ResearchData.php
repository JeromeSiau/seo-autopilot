<?php

namespace App\Services\LLM\DTOs;

class ResearchData
{
    public function __construct(
        public readonly string $keyword,
        public readonly array $competitorInsights,
        public readonly array $keyPointsToCover,
        public readonly array $contentGaps,
        public readonly array $suggestedAngles,
        public readonly ?int $suggestedWordCount = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            keyword: $data['keyword'],
            competitorInsights: $data['competitor_insights'] ?? [],
            keyPointsToCover: $data['key_points_to_cover'] ?? [],
            contentGaps: $data['content_gaps'] ?? [],
            suggestedAngles: $data['suggested_angles'] ?? [],
            suggestedWordCount: isset($data['suggested_word_count']) ? (int) $data['suggested_word_count'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'competitor_insights' => $this->competitorInsights,
            'key_points_to_cover' => $this->keyPointsToCover,
            'content_gaps' => $this->contentGaps,
            'suggested_angles' => $this->suggestedAngles,
            'suggested_word_count' => $this->suggestedWordCount,
        ];
    }

    public function toPromptContext(): string
    {
        $context = "## Research Data for: {$this->keyword}\n\n";

        if (!empty($this->competitorInsights)) {
            $context .= "### Competitor Insights:\n";
            foreach ($this->competitorInsights as $insight) {
                $context .= "- {$insight}\n";
            }
            $context .= "\n";
        }

        if (!empty($this->keyPointsToCover)) {
            $context .= "### Key Points to Cover:\n";
            foreach ($this->keyPointsToCover as $point) {
                $context .= "- {$point}\n";
            }
            $context .= "\n";
        }

        if (!empty($this->contentGaps)) {
            $context .= "### Content Gaps (opportunities):\n";
            foreach ($this->contentGaps as $gap) {
                $context .= "- {$gap}\n";
            }
            $context .= "\n";
        }

        if ($this->suggestedWordCount) {
            $context .= "### Suggested Word Count: {$this->suggestedWordCount}\n";
        }

        return $context;
    }
}
