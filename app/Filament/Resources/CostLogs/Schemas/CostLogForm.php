<?php

namespace App\Filament\Resources\CostLogs\Schemas;

use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Schema;

class CostLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('costable_type')
                    ->required(),
                TextInput::make('costable_id')
                    ->required()
                    ->numeric(),
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                TextInput::make('type')
                    ->required(),
                TextInput::make('provider')
                    ->required(),
                TextInput::make('model')
                    ->default(null),
                TextInput::make('operation')
                    ->required(),
                TextInput::make('cost')
                    ->required()
                    ->numeric()
                    ->prefix('$'),
                TextInput::make('input_tokens')
                    ->numeric()
                    ->default(null),
                TextInput::make('output_tokens')
                    ->numeric()
                    ->default(null),
                Textarea::make('metadata')
                    ->default(null)
                    ->columnSpanFull(),
            ]);
    }
}
