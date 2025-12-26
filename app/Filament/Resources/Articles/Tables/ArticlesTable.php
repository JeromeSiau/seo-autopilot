<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Models\Article;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->tooltip(fn (Article $record) => $record->title),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'ready' => 'info',
                        'generating' => 'warning',
                        'draft' => 'gray',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('word_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('generation_cost')
                    ->money('EUR', divideBy: 1)
                    ->sortable()
                    ->label('Cost'),
                TextColumn::make('llm_used')
                    ->label('Model')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'generating' => 'Generating',
                        'ready' => 'Ready',
                        'published' => 'Published',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('site')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view_published')
                    ->label('View')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Article $record) => $record->published_url)
                    ->openUrlInNewTab()
                    ->visible(fn (Article $record) => $record->published_url),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
