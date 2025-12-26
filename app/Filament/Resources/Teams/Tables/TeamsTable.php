<?php

namespace App\Filament\Resources\Teams\Tables;

use App\Models\Team;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TeamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('owner.email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('billingPlan.name')
                    ->badge()
                    ->label('Plan'),
                TextColumn::make('status')
                    ->badge()
                    ->state(fn (Team $record): string => match (true) {
                        $record->is_trial && ! $record->isTrialExpired() => 'trial',
                        $record->plan_id !== null && ! $record->is_trial => 'active',
                        default => 'inactive',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'trial' => 'warning',
                        'active' => 'success',
                        'inactive' => 'gray',
                    }),
                TextColumn::make('sites_count')
                    ->counts('sites')
                    ->label('Sites'),
                TextColumn::make('articles_used_this_month')
                    ->label('Articles/Month'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'trial' => 'Trial',
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value']) {
                            'trial' => $query->where('is_trial', true)->where(fn ($q) => $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '>', now())),
                            'active' => $query->whereNotNull('plan_id')->where('is_trial', false),
                            'inactive' => $query->where('is_trial', false)->whereNull('plan_id'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('plan_id')
                    ->relationship('billingPlan', 'name')
                    ->label('Plan'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('view_stripe')
                    ->label('Stripe')
                    ->icon('heroicon-o-credit-card')
                    ->url(fn (Team $record) => "https://dashboard.stripe.com/customers/{$record->stripe_id}")
                    ->openUrlInNewTab()
                    ->visible(fn (Team $record) => $record->stripe_id),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
