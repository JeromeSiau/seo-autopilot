<?php

namespace App\Filament\Resources\Teams\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TeamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Client Information')
                    ->description('Basic information about the client team')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Team Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Enter team name'),
                        Select::make('owner_id')
                            ->label('Owner')
                            ->relationship('owner', 'email')
                            ->searchable()
                            ->preload()
                            ->required(),
                    ]),

                Section::make('Subscription')
                    ->description('Billing plan and subscription details')
                    ->icon('heroicon-o-credit-card')
                    ->columns(2)
                    ->schema([
                        Select::make('plan_id')
                            ->label('Billing Plan')
                            ->relationship('billingPlan', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Select a plan'),
                        Toggle::make('is_trial')
                            ->label('Trial Mode')
                            ->default(true)
                            ->inline(false),
                        DateTimePicker::make('trial_ends_at')
                            ->label('Trial Ends At')
                            ->native(false),
                        TextInput::make('articles_limit')
                            ->label('Articles Limit')
                            ->numeric()
                            ->default(10)
                            ->suffix('/ month'),
                    ]),
            ]);
    }
}
