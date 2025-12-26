<?php

namespace App\Filament\Resources\CostLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CostLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('team.name')
                    ->label('Team')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'llm' => 'info',
                        'api' => 'primary',
                        'embedding' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('provider')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('model')
                    ->toggleable(),
                TextColumn::make('operation')
                    ->searchable(),
                TextColumn::make('cost')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        \Filament\Tables\Columns\Summarizers\Sum::make()
                            ->money('USD'),
                    ]),
                TextColumn::make('input_tokens')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('output_tokens')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('costable_type')
                    ->label('Source Type')
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'llm' => 'LLM',
                        'api' => 'API',
                        'embedding' => 'Embedding',
                    ]),
                SelectFilter::make('provider')
                    ->options([
                        'openai' => 'OpenAI',
                        'anthropic' => 'Anthropic',
                        'google' => 'Google',
                    ]),
                SelectFilter::make('team')
                    ->relationship('team', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
