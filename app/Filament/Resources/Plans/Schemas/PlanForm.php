<?php

namespace App\Filament\Resources\Plans\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlanForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Plan Details')
                    ->description('Basic information about this pricing plan')
                    ->icon('heroicon-o-tag')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Plan Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Pro, Agency'),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. pro, agency'),
                        TextInput::make('price')
                            ->label('Monthly Price')
                            ->numeric()
                            ->prefix('€')
                            ->required()
                            ->placeholder('29.00'),
                        TextInput::make('stripe_price_id')
                            ->label('Stripe Price ID')
                            ->placeholder('price_...'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                        TextInput::make('sort_order')
                            ->label('Display Order')
                            ->numeric()
                            ->default(0),
                    ]),

                Section::make('Usage Limits')
                    ->description('Define the limits for this plan. Use -1 for unlimited.')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(2)
                    ->schema([
                        TextInput::make('articles_per_month')
                            ->label('Articles per Month')
                            ->numeric()
                            ->required()
                            ->placeholder('-1 for unlimited'),
                        TextInput::make('sites_limit')
                            ->label('Sites Limit')
                            ->numeric()
                            ->required()
                            ->placeholder('-1 for unlimited'),
                    ]),

                Section::make('Features')
                    ->description('Additional features included in this plan')
                    ->icon('heroicon-o-sparkles')
                    ->collapsed()
                    ->schema([
                        KeyValue::make('features')
                            ->keyLabel('Feature Key')
                            ->valueLabel('Value')
                            ->reorderable()
                            ->addActionLabel('Add Feature'),
                    ]),
            ]);
    }
}
