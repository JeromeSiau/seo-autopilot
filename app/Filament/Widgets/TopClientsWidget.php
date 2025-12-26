<?php

namespace App\Filament\Widgets;

use App\Models\Team;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopClientsWidget extends BaseWidget
{
    protected static ?string $heading = 'Top Clients (This Month)';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Team::query()
                    ->withCount(['sites as articles_count' => function ($query) {
                        $query->join('articles', 'sites.id', '=', 'articles.site_id')
                            ->whereMonth('articles.created_at', now()->month)
                            ->whereYear('articles.created_at', now()->year);
                    }])
                    ->orderByDesc('articles_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Client'),
                Tables\Columns\TextColumn::make('articles_count')
                    ->label('Articles')
                    ->badge()
                    ->color('primary'),
            ])
            ->paginated(false);
    }
}
