<?php

namespace App\Filament\Resources\Integrations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class IntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'wordpress' => 'info',
                        'webflow' => 'primary',
                        'shopify' => 'success',
                        'ghost' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('last_used_at')
                    ->since()
                    ->label('Last Used')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'wordpress' => 'WordPress',
                        'webflow' => 'Webflow',
                        'shopify' => 'Shopify',
                        'ghost' => 'Ghost',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
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
            ]);
    }
}
