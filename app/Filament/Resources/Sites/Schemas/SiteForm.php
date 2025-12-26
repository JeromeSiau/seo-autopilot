<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Site Information')
                    ->description('Basic details about this website')
                    ->icon('heroicon-o-globe-alt')
                    ->columns(2)
                    ->schema([
                        Select::make('team_id')
                            ->label('Team')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->label('Site Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('My Awesome Blog'),
                        TextInput::make('domain')
                            ->label('Domain')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('example.com')
                            ->prefix('https://'),
                        Select::make('language')
                            ->label('Language')
                            ->options([
                                'en' => '🇬🇧 English',
                                'fr' => '🇫🇷 French',
                                'de' => '🇩🇪 German',
                                'es' => '🇪🇸 Spanish',
                            ])
                            ->default('en')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('Content Settings')
                    ->description('Configure how articles should be written for this site')
                    ->icon('heroicon-o-pencil-square')
                    ->columns(2)
                    ->schema([
                        Textarea::make('business_description')
                            ->label('Business Description')
                            ->rows(3)
                            ->placeholder('Describe what this business does...')
                            ->columnSpanFull(),
                        TextInput::make('target_audience')
                            ->label('Target Audience')
                            ->placeholder('e.g. Small business owners, developers'),
                        TextInput::make('tone')
                            ->label('Tone of Voice')
                            ->placeholder('e.g. Professional, Friendly, Casual'),
                        Textarea::make('writing_style')
                            ->label('Writing Style Guidelines')
                            ->rows(2)
                            ->placeholder('Any specific writing guidelines...')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
