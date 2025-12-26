<?php

namespace App\Filament\Resources\AutopilotLogs\Schemas;

use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Schema;

class AutopilotLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->required(),
                TextInput::make('event_type')
                    ->required(),
                Textarea::make('payload')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
