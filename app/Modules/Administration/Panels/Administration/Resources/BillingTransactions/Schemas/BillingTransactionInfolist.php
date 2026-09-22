<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Schemas;

use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class BillingTransactionInfolist
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
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('total'),
                        Infolists\Components\TextEntry::make('tax'),
                        Infolists\Components\TextEntry::make('currency'),
                        Infolists\Components\TextEntry::make('billable.name')
                            ->label(trans('Team'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('paddle_id'),
                        Infolists\Components\TextEntry::make('billed_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
