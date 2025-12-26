<?php

namespace App\Filament\Resources\ScheduledArticles\Pages;

use App\Filament\Resources\ScheduledArticles\ScheduledArticleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditScheduledArticle extends EditRecord
{
    protected static string $resource = ScheduledArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
