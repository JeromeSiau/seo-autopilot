<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use App\Models\CostLog;
use App\Models\Team;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $articlesTotal = Article::count();
        $articlesLast7Days = Article::where('created_at', '>=', now()->subDays(7))->count();

        $teamsTotal = Team::count();
        $teamsTrial = Team::where('is_trial', true)
            ->where(function ($q) {
                $q->whereNull('trial_ends_at')
                  ->orWhere('trial_ends_at', '>', now());
            })->count();
        $teamsActive = Team::whereHas('subscriptions', fn ($q) => $q->active())->count();
        $teamsInactive = $teamsTotal - $teamsTrial - $teamsActive;

        $mrr = Team::whereHas('subscriptions', fn ($q) => $q->active())
            ->with('billingPlan')
            ->get()
            ->sum(fn ($team) => $team->billingPlan?->price ?? 0);

        $costThisMonth = CostLog::thisMonth()->sum('cost');

        return [
            Stat::make('Articles', $articlesTotal)
                ->description("+{$articlesLast7Days} last 7 days")
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('Clients', $teamsTotal)
                ->description("{$teamsTrial}T / {$teamsActive}A / {$teamsInactive}I")
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            Stat::make('MRR', number_format($mrr, 0) . ' €')
                ->description('Monthly recurring revenue')
                ->descriptionIcon('heroicon-m-currency-euro')
                ->color('success'),

            Stat::make('Costs', number_format($costThisMonth, 2) . ' €')
                ->description('This month')
                ->descriptionIcon('heroicon-m-calculator')
                ->color('warning'),
        ];
    }
}
