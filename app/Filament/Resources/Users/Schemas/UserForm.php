<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\Team;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('User Information')
                    ->description('Basic user account details')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Full name'),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('user@example.com'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->minLength(8)
                            ->placeholder('••••••••'),
                        Select::make('current_team_id')
                            ->label('Current Team')
                            ->options(function (?Model $record): array {
                                if (! $record) {
                                    return Team::pluck('name', 'id')->toArray();
                                }

                                return $record->teams->pluck('name', 'id')->toArray();
                            })
                            ->searchable()
                            ->preload()
                            ->placeholder('Select active team'),
                    ]),

                Section::make('Team Memberships')
                    ->description('Teams this user belongs to')
                    ->icon('heroicon-o-user-group')
                    ->schema([
                        Select::make('teams')
                            ->label('Teams')
                            ->relationship('teams', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->placeholder('Select teams'),
                    ]),

                Section::make('Permissions')
                    ->description('Access and role settings')
                    ->icon('heroicon-o-shield-check')
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_admin')
                            ->label('Administrator')
                            ->helperText('Administrators can access this admin panel')
                            ->inline(false),
                    ]),

                Section::make('Preferences')
                    ->description('User preferences and notification settings')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Select::make('locale')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'fr' => 'Français',
                            ])
                            ->default('en')
                            ->native(false),
                        Select::make('theme')
                            ->options([
                                'light' => 'Light',
                                'dark' => 'Dark',
                                'system' => 'System',
                            ])
                            ->default('system')
                            ->native(false),
                        Select::make('notification_email_frequency')
                            ->label('Email Frequency')
                            ->options([
                                'daily' => 'Daily',
                                'weekly' => 'Weekly',
                                'never' => 'Never',
                            ])
                            ->default('daily')
                            ->native(false),
                        Toggle::make('notification_immediate_failures')
                            ->label('Immediate Failure Alerts')
                            ->inline(false),
                    ]),
            ]);
    }
}
