<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Widgets\ChartWidget;

class ClientDistributionWidget extends ChartWidget
{
    protected static ?string $heading = 'Client Distribution';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $trial = Team::where('is_trial', true)
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            })->count();

        $active = Team::whereHas('subscriptions', fn ($q) => $q->active())->count();

        $inactive = Team::count() - $trial - $active;

        return [
            'datasets' => [
                [
                    'data' => [$trial, $active, $inactive],
                    'backgroundColor' => ['#f59e0b', '#10b981', '#6b7280'],
                ],
            ],
            'labels' => ["Trial ({$trial})", "Active ({$active})", "Inactive ({$inactive})"],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
