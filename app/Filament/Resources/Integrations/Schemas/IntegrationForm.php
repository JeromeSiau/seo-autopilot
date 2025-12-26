<?php

namespace App\Filament\Resources\Integrations\Schemas;

use Filament\Schemas\Components\DateTimePicker;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Components\Toggle;
use Filament\Schemas\Schema;

class IntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('team_id')
                    ->relationship('team', 'name')
                    ->required(),
                Select::make('site_id')
                    ->relationship('site', 'name')
                    ->default(null),
                Select::make('type')
                    ->options(['wordpress' => 'Wordpress', 'webflow' => 'Webflow', 'shopify' => 'Shopify', 'ghost' => 'Ghost'])
                    ->required(),
                TextInput::make('name')
                    ->required(),
                Textarea::make('credentials')
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
                DateTimePicker::make('last_used_at'),
            ]);
    }
}
