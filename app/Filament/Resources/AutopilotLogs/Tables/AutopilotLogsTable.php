<?php

namespace App\Filament\Resources\AutopilotLogs\Tables;

use App\Models\AutopilotLog;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AutopilotLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.name')
                    ->label('Site')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        AutopilotLog::TYPE_KEYWORD_DISCOVERED => 'info',
                        AutopilotLog::TYPE_ARTICLE_GENERATED => 'success',
                        AutopilotLog::TYPE_ARTICLE_PUBLISHED => 'primary',
                        AutopilotLog::TYPE_PUBLISH_FAILED => 'danger',
                        AutopilotLog::TYPE_KEYWORDS_IMPORTED => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        AutopilotLog::TYPE_KEYWORD_DISCOVERED => 'Keyword Discovered',
                        AutopilotLog::TYPE_ARTICLE_GENERATED => 'Article Generated',
                        AutopilotLog::TYPE_ARTICLE_PUBLISHED => 'Article Published',
                        AutopilotLog::TYPE_PUBLISH_FAILED => 'Publish Failed',
                        AutopilotLog::TYPE_KEYWORDS_IMPORTED => 'Keywords Imported',
                        default => $state,
                    }),
                TextColumn::make('payload')
                    ->label('Details')
                    ->formatStateUsing(function (?array $state): string {
                        if (empty($state)) {
                            return '-';
                        }
                        // Show summary of payload
                        $parts = [];
                        if (isset($state['keyword'])) {
                            $parts[] = "Keyword: {$state['keyword']}";
                        }
                        if (isset($state['article_title'])) {
                            $parts[] = "Article: {$state['article_title']}";
                        }
                        if (isset($state['count'])) {
                            $parts[] = "Count: {$state['count']}";
                        }
                        if (isset($state['error'])) {
                            $parts[] = "Error: {$state['error']}";
                        }
                        return implode(', ', $parts) ?: json_encode($state);
                    })
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        AutopilotLog::TYPE_KEYWORD_DISCOVERED => 'Keyword Discovered',
                        AutopilotLog::TYPE_ARTICLE_GENERATED => 'Article Generated',
                        AutopilotLog::TYPE_ARTICLE_PUBLISHED => 'Article Published',
                        AutopilotLog::TYPE_PUBLISH_FAILED => 'Publish Failed',
                        AutopilotLog::TYPE_KEYWORDS_IMPORTED => 'Keywords Imported',
                    ]),
                SelectFilter::make('site')
                    ->relationship('site', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
