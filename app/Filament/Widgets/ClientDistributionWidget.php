<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Widgets\ChartWidget;

class ClientDistributionWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    public function getHeading(): string
    {
        return 'Client Distribution';
    }

    protected function getData(): array
    {
        // Active trial teams
        $trial = Team::where('is_trial', true)
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            })->count();

        // Teams with a billing plan assigned (active subscribers)
        $active = Team::whereNotNull('plan_id')
            ->where('is_trial', false)
            ->count();

        // Expired trials or no plan
        $inactive = Team::where(function ($q) {
            $q->where('is_trial', true)
              ->whereNotNull('trial_ends_at')
              ->where('trial_ends_at', '<=', now());
        })->orWhere(function ($q) {
            $q->where('is_trial', false)
              ->whereNull('plan_id');
        })->count();

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
