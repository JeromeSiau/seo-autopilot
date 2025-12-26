<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Client Information')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('owner_id')
                            ->relationship('owner', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('plan_id')
                            ->relationship('billingPlan', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(2),

                Section::make('Trial & Limits')
                    ->schema([
                        Toggle::make('is_trial')
                            ->default(true),
                        DateTimePicker::make('trial_ends_at'),
                        TextInput::make('articles_limit')
                            ->numeric()
                            ->default(10),
                    ])->columns(3),
            ]);
    }
}
