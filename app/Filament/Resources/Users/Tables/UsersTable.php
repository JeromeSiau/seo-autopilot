<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('No teams'),
                TextColumn::make('currentTeam.name')
                    ->label('Active Team')
                    ->placeholder('-'),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('is_admin')
                    ->label('Admin Status'),
                SelectFilter::make('teams')
                    ->label('Team')
                    ->relationship('teams', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
