<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Tables;

use App\Modules\Billing\Models\Subscription;
use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class SubscriptionsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getRecordActions());
    }

    /**
     * Get the table columns.
     *
     * @return array<int, \Filament\Tables\Columns\Layout\Split>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\Layout\Split::make([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('billable.name')
                        ->label(trans('Team'))
                        ->weight(FontWeight::Medium)
                        ->placeholder('-'),
                    Tables\Columns\TextColumn::make('paddle_id')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ]),
        ];
    }

    /**
     * Get the table filters.
     *
     * @return array<int, \Filament\Tables\Filters\BaseFilter>
     */
    protected static function getFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('status')
                ->options(fn (): array => Subscription::query()
                    ->distinct()
                    ->pluck('status', 'status')
                    ->all()),
        ];
    }

    /**
     * Get the row actions for the table.
     *
     * @return array<int, \Filament\Actions\ActionGroup>
     */
    protected static function getRecordActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\ViewAction::make(),
            ]),
        ];
    }
}
