<?php

namespace App\Filament\Resources\ScheduledArticles\Pages;

use App\Filament\Resources\ScheduledArticles\ScheduledArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListScheduledArticles extends ListRecords
{
    protected static string $resource = ScheduledArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
