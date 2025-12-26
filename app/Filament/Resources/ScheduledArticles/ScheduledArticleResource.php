<?php

namespace App\Filament\Resources\ScheduledArticles;

use App\Filament\Resources\ScheduledArticles\Pages\CreateScheduledArticle;
use App\Filament\Resources\ScheduledArticles\Pages\EditScheduledArticle;
use App\Filament\Resources\ScheduledArticles\Pages\ListScheduledArticles;
use App\Filament\Resources\ScheduledArticles\Schemas\ScheduledArticleForm;
use App\Filament\Resources\ScheduledArticles\Tables\ScheduledArticlesTable;
use App\Models\ScheduledArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScheduledArticleResource extends Resource
{
    protected static ?string $model = ScheduledArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return ScheduledArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScheduledArticlesTable::configure($table);
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
            'index' => ListScheduledArticles::route('/'),
            'create' => CreateScheduledArticle::route('/create'),
            'edit' => EditScheduledArticle::route('/{record}/edit'),
        ];
    }
}
