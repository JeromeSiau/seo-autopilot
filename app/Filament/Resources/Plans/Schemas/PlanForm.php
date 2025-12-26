<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('price')
                            ->numeric()
                            ->prefix('€')
                            ->suffix('/month')
                            ->required(),
                        TextInput::make('stripe_price_id')
                            ->label('Stripe Price ID'),
                    ])->columns(2),

                Section::make('Limits')
                    ->schema([
                        TextInput::make('articles_per_month')
                            ->numeric()
                            ->required()
                            ->helperText('-1 for unlimited'),
                        TextInput::make('sites_limit')
                            ->numeric()
                            ->required()
                            ->helperText('-1 for unlimited'),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->default(true),
                    ])->columns(2),

                Section::make('Features')
                    ->schema([
                        KeyValue::make('features')
                            ->keyLabel('Feature Key')
                            ->valueLabel('Value')
                            ->reorderable(),
                    ]),
            ]);
    }
}
