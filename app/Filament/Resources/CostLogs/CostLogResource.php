<?php

namespace App\Filament\Resources\CostLogs;

use App\Filament\Resources\CostLogs\Pages\CreateCostLog;
use App\Filament\Resources\CostLogs\Pages\EditCostLog;
use App\Filament\Resources\CostLogs\Pages\ListCostLogs;
use App\Filament\Resources\CostLogs\Schemas\CostLogForm;
use App\Filament\Resources\CostLogs\Tables\CostLogsTable;
use App\Models\CostLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CostLogResource extends Resource
{
    protected static ?string $model = CostLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static \UnitEnum|string|null $navigationGroup = 'Logs';

    protected static ?int $navigationSort = 8;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CostLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CostLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCostLogs::route('/'),
        ];
    }
}
