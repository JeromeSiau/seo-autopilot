<?php

namespace App\Filament\Widgets;

use App\Models\Article;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentFailuresWidget extends BaseWidget
{
    protected static ?string $heading = 'Recent Failures';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Article::query()
                    ->where('status', 'failed')
                    ->orderByDesc('created_at')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->title),
                Tables\Columns\TextColumn::make('error_message')
                    ->limit(20)
                    ->tooltip(fn ($record) => $record->error_message)
                    ->color('danger'),
                Tables\Columns\TextColumn::make('created_at')
                    ->since()
                    ->label('When'),
            ])
            ->paginated(false);
    }
}
