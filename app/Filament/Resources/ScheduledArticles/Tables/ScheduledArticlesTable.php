<?php

namespace App\Filament\Resources\ScheduledArticles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ScheduledArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('keyword.keyword')
                    ->label('Keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('scheduled_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'generating' => 'warning',
                        'planned' => 'info',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('article.title')
                    ->label('Article')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'planned' => 'Planned',
                        'generating' => 'Generating',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('site')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_date', 'desc');
    }
}
