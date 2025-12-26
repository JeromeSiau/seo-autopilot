<?php

namespace App\Filament\Resources\Articles\Schemas;

use Filament\Schemas\Components\RichEditor;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Select;
use Filament\Schemas\Components\Textarea;
use Filament\Schemas\Components\TextInput;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Article Information')
                    ->schema([
                        Select::make('site_id')
                            ->relationship('site', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->maxLength(255),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'generating' => 'Generating',
                                'ready' => 'Ready',
                                'published' => 'Published',
                                'failed' => 'Failed',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(2),

                Section::make('Content')
                    ->schema([
                        RichEditor::make('content')
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO')
                    ->schema([
                        TextInput::make('meta_title')
                            ->maxLength(60),
                        Textarea::make('meta_description')
                            ->maxLength(160)
                            ->rows(2),
                    ])->columns(2),

                Section::make('Generation Info')
                    ->schema([
                        TextInput::make('llm_used')
                            ->label('Model')
                            ->disabled(),
                        TextInput::make('generation_cost')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        TextInput::make('word_count')
                            ->numeric()
                            ->disabled(),
                        TextInput::make('generation_time_seconds')
                            ->label('Generation Time (s)')
                            ->numeric()
                            ->disabled(),
                    ])->columns(4)
                    ->collapsed(),
            ]);
    }
}
