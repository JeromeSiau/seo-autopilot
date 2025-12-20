<?php

namespace App\Services\Keyword;

use App\Models\Keyword;
use App\Services\SEO\DTOs\KeywordData;

class KeywordScoringService
{
    // Weights for scoring
    private const VOLUME_WEIGHT = 0.3;
    private const DIFFICULTY_WEIGHT = 0.3;
    private const QUICK_WIN_WEIGHT = 0.25;
    private const RELEVANCE_WEIGHT = 0.15;

    /**
     * Calculate priority score for a keyword.
     */
    public function calculateScore(
        Keyword $keyword,
        ?float $currentPosition = null,
        ?float $relevanceScore = null,
    ): float {
        $volumeScore = $this->normalizeVolume($keyword->volume ?? 0);
        $difficultyScore = $this->normalizeDifficulty($keyword->difficulty ?? 50);
        $quickWinScore = $this->calculateQuickWinBonus($currentPosition);
        $relevance = $relevanceScore ?? 50;

        $score = ($volumeScore * self::VOLUME_WEIGHT) +
                 ($difficultyScore * self::DIFFICULTY_WEIGHT) +
                 ($quickWinScore * self::QUICK_WIN_WEIGHT) +
                 ($relevance * self::RELEVANCE_WEIGHT);

        return round($score, 2);
    }

    /**
     * Calculate score from KeywordData DTO.
     */
    public function calculateScoreFromData(
        KeywordData $data,
        ?float $currentPosition = null,
        ?float $relevanceScore = null,
    ): float {
        $volumeScore = $this->normalizeVolume($data->volume);
        $difficultyScore = $this->normalizeDifficulty($data->difficulty);
        $quickWinScore = $this->calculateQuickWinBonus($currentPosition);
        $relevance = $relevanceScore ?? 50;

        $score = ($volumeScore * self::VOLUME_WEIGHT) +
                 ($difficultyScore * self::DIFFICULTY_WEIGHT) +
                 ($quickWinScore * self::QUICK_WIN_WEIGHT) +
                 ($relevance * self::RELEVANCE_WEIGHT);

        return round($score, 2);
    }

    /**
     * Normalize volume to 0-100 scale (log scale).
     */
    private function normalizeVolume(int $volume): float
    {
        if ($volume <= 0) {
            return 0;
        }

        // Log scale normalization
        // Volume of 10 -> ~25, 100 -> ~50, 1000 -> ~75, 10000 -> ~100
        return min(100, log10($volume) * 25);
    }

    /**
     * Normalize difficulty (invert so lower difficulty = higher score).
     */
    private function normalizeDifficulty(float $difficulty): float
    {
        // Difficulty is 0-100, we want 0-100 inverted
        return max(0, 100 - $difficulty);
    }

    /**
     * Calculate quick win bonus based on current position.
     */
    private function calculateQuickWinBonus(?float $position): float
    {
        if ($position === null) {
            return 0;
        }

        // Positions 5-20 are optimal quick wins
        if ($position >= 5 && $position <= 10) {
            return 100; // Maximum bonus
        }

        if ($position > 10 && $position <= 20) {
            return 80;
        }

        if ($position > 20 && $position <= 30) {
            return 60;
        }

        if ($position > 30 && $position <= 50) {
            return 30;
        }

        // Already ranking top 5 - less value in new content
        if ($position < 5) {
            return 20;
        }

        return 0;
    }

    /**
     * Batch update scores for multiple keywords.
     */
    public function updateScores(array $keywordIds): void
    {
        $keywords = Keyword::whereIn('id', $keywordIds)->get();

        foreach ($keywords as $keyword) {
            $score = $this->calculateScore($keyword);
            $keyword->update(['score' => $score]);
        }
    }

    /**
     * Get priority order for keywords.
     */
    public function getPriorityOrder(array $keywords): array
    {
        usort($keywords, fn($a, $b) => $b['score'] <=> $a['score']);
        return $keywords;
    }
}
