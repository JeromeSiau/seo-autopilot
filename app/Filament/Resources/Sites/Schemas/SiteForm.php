<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site Information')
                    ->schema([
                        Select::make('team_id')
                            ->relationship('team', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('domain')
                            ->required()
                            ->url(false)
                            ->maxLength(255),
                        Select::make('language')
                            ->options([
                                'en' => 'English',
                                'fr' => 'French',
                                'de' => 'German',
                                'es' => 'Spanish',
                            ])
                            ->default('en')
                            ->required(),
                    ])->columns(2),

                Section::make('Content Settings')
                    ->schema([
                        Textarea::make('business_description')
                            ->rows(3),
                        TextInput::make('target_audience'),
                        TextInput::make('tone'),
                        Textarea::make('writing_style')
                            ->rows(2),
                    ])->columns(2),
            ]);
    }
}
