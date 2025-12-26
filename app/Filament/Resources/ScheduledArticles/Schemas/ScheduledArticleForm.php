<?php

namespace App\Filament\Resources\ScheduledArticles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class ScheduledArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                Select::make('keyword_id')
                    ->relationship('keyword', 'id')
                    ->required(),
                DatePicker::make('scheduled_date')
                    ->required(),
                Select::make('status')
                    ->options([
            'planned' => 'Planned',
            'generating' => 'Generating',
            'ready' => 'Ready',
            'published' => 'Published',
            'skipped' => 'Skipped',
        ])
                    ->default('planned')
                    ->required(),
                Select::make('article_id')
                    ->relationship('article', 'title')
                    ->default(null),
            ]);
    }
}
