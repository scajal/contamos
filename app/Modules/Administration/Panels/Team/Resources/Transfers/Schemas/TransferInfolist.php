<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Schemas;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Support\Helpers\Amount;
use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class TransferInfolist
{
    /**
     * Configure the resource's infolist.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Schemas\Components\Section::make()
                    ->columns(2)
                    ->schema([
                        Infolists\Components\TextEntry::make('description')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('occurred_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('origin')
                            ->label(trans('Origin'))
                            ->state(fn (Transfer $record): string => self::leg($record->originLeg())),
                        Infolists\Components\TextEntry::make('destination')
                            ->label(trans('Destination'))
                            ->state(fn (Transfer $record): string => self::leg($record->destinationLeg())),
                        Infolists\Components\TextEntry::make('user.email')
                            ->label(trans('Recorded by'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('tags.name')
                            ->label(trans('Tags'))
                            ->placeholder('-')
                            ->badge(),
                    ]),
            ]);
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
}
