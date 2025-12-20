<?php

namespace App\Services\Google\DTOs;

readonly class GA4Report
{
    public function __construct(
        public string $pagePath,
        public int $sessions,
        public int $pageviews,
        public float $avgSessionDuration,
        public float $bounceRate,
        public int $conversions,
        public ?string $date = null,
    ) {}

    public function toArray(): array
    {
        return [
            'page_path' => $this->pagePath,
            'sessions' => $this->sessions,
            'pageviews' => $this->pageviews,
            'avg_session_duration' => round($this->avgSessionDuration, 1),
            'bounce_rate' => round($this->bounceRate * 100, 2),
            'conversions' => $this->conversions,
            'date' => $this->date,
        ];
    }

    public static function fromApiRow(array $dimensions, array $metrics): self
    {
        return new self(
            pagePath: $dimensions['pagePath'] ?? '',
            sessions: (int) ($metrics['sessions'] ?? 0),
            pageviews: (int) ($metrics['screenPageViews'] ?? 0),
            avgSessionDuration: (float) ($metrics['averageSessionDuration'] ?? 0),
            bounceRate: (float) ($metrics['bounceRate'] ?? 0),
            conversions: (int) ($metrics['conversions'] ?? 0),
            date: $dimensions['date'] ?? null,
        );
    }
}
