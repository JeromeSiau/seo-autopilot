<?php

namespace App\Services\Google\DTOs;

readonly class SearchAnalyticsRow
{
    public function __construct(
        public string $query,
        public string $page,
        public float $clicks,
        public float $impressions,
        public float $ctr,
        public float $position,
        public ?string $date = null,
        public ?string $country = null,
        public ?string $device = null,
    ) {}

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'page' => $this->page,
            'clicks' => $this->clicks,
            'impressions' => $this->impressions,
            'ctr' => round($this->ctr * 100, 2),
            'position' => round($this->position, 1),
            'date' => $this->date,
            'country' => $this->country,
            'device' => $this->device,
        ];
    }

    public static function fromApiRow(array $row, array $dimensions): self
    {
        $keys = array_map(fn($d) => strtolower($d), $dimensions);
        $values = $row['keys'] ?? [];

        $data = array_combine($keys, $values);

        return new self(
            query: $data['query'] ?? '',
            page: $data['page'] ?? '',
            clicks: $row['clicks'] ?? 0,
            impressions: $row['impressions'] ?? 0,
            ctr: $row['ctr'] ?? 0,
            position: $row['position'] ?? 0,
            date: $data['date'] ?? null,
            country: $data['country'] ?? null,
            device: $data['device'] ?? null,
        );
    }

    /**
     * Check if this is a "quick win" keyword (position 5-30).
     */
    public function isQuickWin(): bool
    {
        return $this->position >= 5 && $this->position <= 30;
    }

    /**
     * Check if this has high impressions but low clicks (opportunity).
     */
    public function isOpportunity(int $minImpressions = 100, float $maxCtr = 0.02): bool
    {
        return $this->impressions >= $minImpressions && $this->ctr <= $maxCtr;
    }
}
