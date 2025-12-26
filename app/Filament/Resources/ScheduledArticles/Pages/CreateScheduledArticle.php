<?php

namespace App\Filament\Resources\ScheduledArticles\Pages;

use App\Filament\Resources\ScheduledArticles\ScheduledArticleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScheduledArticle extends CreateRecord
{
    protected static string $resource = ScheduledArticleResource::class;
}
