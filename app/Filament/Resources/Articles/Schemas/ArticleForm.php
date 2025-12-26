<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Article Details')
                    ->description('Basic information about this article')
                    ->icon('heroicon-o-document-text')
                    ->columns(2)
                    ->schema([
                        Select::make('site_id')
                            ->label('Site')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'generating' => 'Generating',
                                'ready' => 'Ready',
                                'published' => 'Published',
                                'failed' => 'Failed',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Article title')
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->placeholder('article-slug')
                            ->columnSpanFull(),
                    ]),

                Section::make('Content')
                    ->description('The main article content')
                    ->icon('heroicon-o-pencil')
                    ->schema([
                        RichEditor::make('content')
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'strike',
                                'h2', 'h3',
                                'bulletList', 'orderedList',
                                'link', 'blockquote',
                                'undo', 'redo',
                            ])
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO Settings')
                    ->description('Search engine optimization metadata')
                    ->icon('heroicon-o-magnifying-glass')
                    ->columns(1)
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('Meta Title')
                            ->maxLength(60)
                            ->placeholder('SEO title (max 60 characters)')
                            ->hint(fn ($state) => strlen($state ?? '') . '/60'),
                        Textarea::make('meta_description')
                            ->label('Meta Description')
                            ->maxLength(160)
                            ->rows(2)
                            ->placeholder('SEO description (max 160 characters)')
                            ->hint(fn ($state) => strlen($state ?? '') . '/160'),
                    ]),

                Section::make('Generation Info')
                    ->description('Technical details about how this article was generated')
                    ->icon('heroicon-o-cpu-chip')
                    ->columns(4)
                    ->collapsed()
                    ->schema([
                        TextInput::make('llm_used')
                            ->label('Model')
                            ->disabled(),
                        TextInput::make('generation_cost')
                            ->label('Cost')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        TextInput::make('word_count')
                            ->label('Words')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('generation_time_seconds')
                            ->label('Time (s)')
                            ->numeric()
                            ->disabled(),
                    ]),
            ]);
    }
}
