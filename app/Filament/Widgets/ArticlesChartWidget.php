<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class ArticlesChartWidget extends ChartWidget
{
    protected static ?int $sort = 2;

    public function getHeading(): string
    {
        return 'Articles (30 days)';
    }

    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($i) => now()->subDays($i)->format('Y-m-d'));

        $generated = Article::where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $published = Article::where('status', 'published')
            ->where('published_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(published_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $failed = Article::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Generated',
                    'data' => $days->map(fn ($d) => $generated[$d] ?? 0)->values(),
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                ],
                [
                    'label' => 'Published',
                    'data' => $days->map(fn ($d) => $published[$d] ?? 0)->values(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Failed',
                    'data' => $days->map(fn ($d) => $failed[$d] ?? 0)->values(),
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                ],
            ],
            'labels' => $days->map(fn ($d) => Carbon::parse($d)->format('M d'))->values(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
