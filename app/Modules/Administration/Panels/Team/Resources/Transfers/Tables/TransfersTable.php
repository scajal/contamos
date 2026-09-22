<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Tables;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Support\Helpers\Amount;
use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class TransfersTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('occurred_at', 'desc')
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
                    Tables\Columns\TextColumn::make('description')
                        ->weight(FontWeight::Medium)
                        ->placeholder('-')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('occurred_at')
                        ->dateTime(),
                ]),
                Tables\Columns\TextColumn::make('origin')
                    ->label(trans('Origin'))
                    ->state(fn (Transfer $record): string => self::leg($record->originLeg())),
                Tables\Columns\TextColumn::make('destination')
                    ->label(trans('Destination'))
                    ->state(fn (Transfer $record): string => self::leg($record->destinationLeg())),
            ]),
        ];
    }

    /**
     * Describe one leg of the transfer as its account and amount.
     */
    protected static function leg(?Transaction $transaction): string
    {
        if (! $transaction instanceof Transaction) {
            return '-';
        }

        return $transaction->account->name.' · '.new Amount()->format((string) $transaction->amount, $transaction->currency);
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
