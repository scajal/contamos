<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Schemas;

use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Support\Helpers\Amount;
use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class TransactionInfolist
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
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('amount')
                            ->state(fn (Transaction $record): string => new Amount()->format((string) $record->amount, $record->currency)),
                        Infolists\Components\TextEntry::make('quote'),
                        Infolists\Components\TextEntry::make('account.name'),
                        Infolists\Components\TextEntry::make('currency.code'),
                        Infolists\Components\TextEntry::make('occurred_at')
                            ->dateTime(),
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
}
