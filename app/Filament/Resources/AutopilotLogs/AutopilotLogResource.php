<?php

namespace App\Filament\Resources\AutopilotLogs;

use App\Filament\Resources\AutopilotLogs\Pages\ListAutopilotLogs;
use App\Filament\Resources\AutopilotLogs\Tables\AutopilotLogsTable;
use App\Models\AutopilotLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AutopilotLogResource extends Resource
{
    protected static ?string $model = AutopilotLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static \UnitEnum|string|null $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 9;

    public static function table(Table $table): Table
    {
        return AutopilotLogsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAutopilotLogs::route('/'),
        ];
    }
}
