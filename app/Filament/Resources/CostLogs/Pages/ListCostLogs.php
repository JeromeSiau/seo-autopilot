<?php

namespace App\Filament\Resources\CostLogs\Pages;

use App\Filament\Resources\CostLogs\CostLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCostLogs extends ListRecords
{
    protected static string $resource = CostLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
