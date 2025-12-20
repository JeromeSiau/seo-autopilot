<?php

namespace App\Services\SEO\DTOs;

readonly class KeywordData
{
    public function __construct(
        public string $keyword,
        public int $volume,
        public float $difficulty,
        public float $cpc,
        public ?float $competition = null,
        public ?array $trend = null,
        public ?string $language = null,
        public ?string $location = null,
    ) {}

    public function toArray(): array
    {
        return [
            'keyword' => $this->keyword,
            'volume' => $this->volume,
            'difficulty' => round($this->difficulty, 1),
            'cpc' => round($this->cpc, 2),
            'competition' => $this->competition ? round($this->competition, 2) : null,
            'trend' => $this->trend,
            'language' => $this->language,
            'location' => $this->location,
        ];
    }

    public static function fromDataForSEO(array $data): self
    {
        return new self(
            keyword: $data['keyword'] ?? '',
            volume: $data['search_volume'] ?? 0,
            difficulty: $data['keyword_difficulty'] ?? 0,
            cpc: $data['cpc'] ?? 0,
            competition: $data['competition'] ?? null,
            trend: $data['monthly_searches'] ?? null,
            language: $data['language_code'] ?? null,
            location: $data['location_code'] ?? null,
        );
    }

    /**
     * Calculate a composite score for prioritization.
     */
    public function getScore(float $volumeWeight = 0.3, float $difficultyWeight = 0.4, float $cpcWeight = 0.3): float
    {
        // Normalize volume (0-100 scale, log scale for large volumes)
        $volumeScore = min(100, log10(max(1, $this->volume)) * 25);

        // Difficulty is already 0-100, invert so lower difficulty = higher score
        $difficultyScore = 100 - $this->difficulty;

        // Normalize CPC (0-100 scale, cap at $10)
        $cpcScore = min(100, $this->cpc * 10);

        return ($volumeScore * $volumeWeight) +
               ($difficultyScore * $difficultyWeight) +
               ($cpcScore * $cpcWeight);
    }
}
