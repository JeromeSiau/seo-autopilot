<?php

namespace App\Filament\Resources\ScheduledArticles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ScheduledArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Schedule Details')
                    ->description('Configure when and where the article will be published')
                    ->icon('heroicon-o-calendar')
                    ->columns(2)
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('scheduled_date')
                            ->label('Scheduled Date')
                            ->required()
                            ->native(false),
                        Select::make('keyword_id')
                            ->label('Target Keyword')
                            ->relationship('keyword', 'keyword')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),
                    ]),

                Section::make('Status & Article')
                    ->description('Current status and linked article')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        Select::make('status')
                            ->options([
                                'planned' => 'Planned',
                                'generating' => 'Generating',
                                'ready' => 'Ready',
                                'published' => 'Published',
                                'skipped' => 'Skipped',
                            ])
                            ->default('planned')
                            ->required()
                            ->native(false),
                        Select::make('article_id')
                            ->label('Generated Article')
                            ->relationship('article', 'title')
                            ->searchable()
                            ->preload()
                            ->placeholder('No article linked yet')
                            ->native(false),
                    ]),
            ]);
    }
}
