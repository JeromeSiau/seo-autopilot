<?php

namespace App\Filament\Resources\Integrations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Integration Setup')
                    ->description('Configure the CMS integration')
                    ->icon('heroicon-o-puzzle-piece')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Integration Name')
                            ->required()
                            ->placeholder('e.g. My WordPress Blog'),
                        Select::make('type')
                            ->label('Platform')
                            ->options([
                                'wordpress' => 'WordPress',
                                'webflow' => 'Webflow',
                                'shopify' => 'Shopify',
                                'ghost' => 'Ghost',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('team_id')
                            ->label('Team')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('Optional - link to a site'),
                    ]),

                Section::make('Credentials')
                    ->description('API credentials for the integration (stored encrypted)')
                    ->icon('heroicon-o-key')
                    ->schema([
                        Textarea::make('credentials')
                            ->label('API Credentials (JSON)')
                            ->required()
                            ->rows(4)
                            ->placeholder('{"api_key": "...", "api_secret": "..."}')
                            ->columnSpanFull(),
                    ]),

                Section::make('Status')
                    ->description('Integration status')
                    ->icon('heroicon-o-signal')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->inline(false),
                    ]),
            ]);
    }
}
