<?php

namespace App\Filament\Resources\Keywords\Schemas;

use Filament\Schemas\Components\DatePicker;
use Filament\Schemas\Components\DateTimePicker;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;

class KeywordForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                TextInput::make('keyword')
                    ->required(),
                TextInput::make('volume')
                    ->numeric()
                    ->default(null),
                TextInput::make('difficulty')
                    ->numeric()
                    ->default(null),
                TextInput::make('cpc')
                    ->numeric()
                    ->default(null),
                Select::make('status')
                    ->options([
            'pending' => 'Pending',
            'scheduled' => 'Scheduled',
            'generating' => 'Generating',
            'completed' => 'Completed',
            'skipped' => 'Skipped',
        ])
                    ->default('pending')
                    ->required(),
                TextInput::make('cluster_id')
                    ->default(null),
                Select::make('source')
                    ->options([
            'search_console' => 'Search console',
            'ai_generated' => 'Ai generated',
            'manual' => 'Manual',
            'dataforseo' => 'Dataforseo',
        ])
                    ->default('manual')
                    ->required(),
                TextInput::make('current_position')
                    ->numeric()
                    ->default(null),
                TextInput::make('score')
                    ->numeric()
                    ->default(null),
                DateTimePicker::make('queued_at'),
                DateTimePicker::make('processed_at'),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('impressions')
                    ->numeric()
                    ->default(null),
                DatePicker::make('scheduled_for'),
            ]);
    }
}
