<?php

namespace App\Filament\Resources\CostLogs\Pages;

use App\Filament\Resources\CostLogs\CostLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCostLog extends EditRecord
{
    protected static string $resource = CostLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
