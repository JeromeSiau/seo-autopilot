<?php

namespace App\Filament\Resources\AutopilotLogs\Pages;

use App\Filament\Resources\AutopilotLogs\AutopilotLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAutopilotLogs extends ListRecords
{
    protected static string $resource = AutopilotLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
