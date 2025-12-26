<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Models\Site;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.name')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('domain')
                    ->searchable()
                    ->url(fn (Site $record) => "https://{$record->domain}", shouldOpenInNewTab: true),
                TextColumn::make('language')
                    ->badge(),
                IconColumn::make('gsc_connected')
                    ->label('GSC')
                    ->boolean(),
                IconColumn::make('autopilot')
                    ->state(fn (Site $record) => $record->settings?->autopilot_enabled ?? false)
                    ->boolean()
                    ->label('Autopilot'),
                TextColumn::make('articles_count')
                    ->counts('articles')
                    ->label('Articles'),
                TextColumn::make('keywords_count')
                    ->counts('keywords')
                    ->label('Keywords'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('team')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('language')
                    ->options([
                        'en' => 'English',
                        'fr' => 'French',
                        'de' => 'German',
                        'es' => 'Spanish',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
