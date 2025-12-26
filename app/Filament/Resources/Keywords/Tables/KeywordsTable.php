<?php

namespace App\Filament\Resources\Keywords\Tables;

use App\Models\Keyword;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class KeywordsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'generating' => 'warning',
                        'scheduled' => 'info',
                        'queued' => 'primary',
                        'pending' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('volume')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('difficulty')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 30 => 'success',
                        $state <= 60 => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('score')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('has_article')
                    ->label('Article')
                    ->state(fn (Keyword $record) => $record->article()->exists())
                    ->boolean(),
                TextColumn::make('source')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'queued' => 'Queued',
                        'scheduled' => 'Scheduled',
                        'generating' => 'Generating',
                        'completed' => 'Completed',
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
            ->defaultSort('score', 'desc');
    }
}
