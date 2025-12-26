<?php

namespace App\Filament\Resources\Keywords\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class KeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Keyword Details')
                    ->description('Basic keyword information')
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(2)
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'scheduled' => 'Scheduled',
                                'generating' => 'Generating',
                                'completed' => 'Completed',
                                'skipped' => 'Skipped',
                            ])
                            ->default('pending')
                            ->required()
                            ->native(false),
                        TextInput::make('keyword')
                            ->required()
                            ->placeholder('Enter keyword')
                            ->columnSpanFull(),
                        Select::make('source')
                            ->options([
                                'search_console' => 'Search Console',
                                'ai_generated' => 'AI Generated',
                                'manual' => 'Manual',
                                'dataforseo' => 'DataForSEO',
                            ])
                            ->default('manual')
                            ->required()
                            ->native(false),
                        DatePicker::make('scheduled_for')
                            ->label('Scheduled For')
                            ->native(false),
                    ]),

                Section::make('SEO Metrics')
                    ->description('Search volume and difficulty data')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(4)
                    ->schema([
                        TextInput::make('volume')
                            ->label('Search Volume')
                            ->numeric()
                            ->placeholder('0'),
                        TextInput::make('difficulty')
                            ->label('Difficulty')
                            ->numeric()
                            ->placeholder('0-100'),
                        TextInput::make('cpc')
                            ->label('CPC')
                            ->numeric()
                            ->prefix('€')
                            ->placeholder('0.00'),
                        TextInput::make('score')
                            ->label('Score')
                            ->numeric()
                            ->placeholder('0'),
                    ]),

                Section::make('Advanced')
                    ->description('Additional keyword settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextInput::make('current_position')
                            ->label('Current Position')
                            ->numeric(),
                        TextInput::make('impressions')
                            ->numeric(),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0),
                        TextInput::make('cluster_id')
                            ->label('Cluster ID'),
                    ]),
            ]);
    }
}
