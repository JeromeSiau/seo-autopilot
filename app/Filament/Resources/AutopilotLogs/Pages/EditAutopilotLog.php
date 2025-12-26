<?php

namespace App\Filament\Resources\AutopilotLogs\Pages;

use App\Filament\Resources\AutopilotLogs\AutopilotLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAutopilotLog extends EditRecord
{
    protected static string $resource = AutopilotLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
