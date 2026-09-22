<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Tables;

use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class BillingTransactionsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('billed_at', 'desc')
            ->columns(self::getColumns())
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
                    Tables\Columns\TextColumn::make('invoice_number')
                        ->placeholder('-')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('total'),
                Tables\Columns\TextColumn::make('billed_at')
                    ->dateTime(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
            ]),
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
