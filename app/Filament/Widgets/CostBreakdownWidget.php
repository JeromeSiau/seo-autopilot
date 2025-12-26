<?php

namespace App\Filament\Widgets;

use App\Models\CostLog;
use Filament\Widgets\ChartWidget;

class CostBreakdownWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    public function getHeading(): string
    {
        return 'Cost Breakdown (This Month)';
    }

    protected function getData(): array
    {
        $costs = CostLog::thisMonth()
            ->selectRaw('type, SUM(cost) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $llm = $costs['llm'] ?? 0;
        $image = $costs['image'] ?? 0;
        $api = $costs['api'] ?? 0;

        return [
            'datasets' => [
                [
                    'data' => [$llm, $image, $api],
                    'backgroundColor' => ['#6366f1', '#8b5cf6', '#ec4899'],
                ],
            ],
            'labels' => [
                'LLM (' . number_format($llm, 2) . ' €)',
                'Images (' . number_format($image, 2) . ' €)',
                'APIs (' . number_format($api, 2) . ' €)',
            ],
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
