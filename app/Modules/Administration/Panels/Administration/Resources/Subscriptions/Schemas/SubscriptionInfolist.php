<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Subscriptions\Schemas;

use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class SubscriptionInfolist
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
                        Infolists\Components\TextEntry::make('type'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge(),
                        Infolists\Components\TextEntry::make('paddle_id'),
                        Infolists\Components\TextEntry::make('billable.name')
                            ->label(trans('Team'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('trial_ends_at')
                            ->placeholder('-')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('paused_at')
                            ->placeholder('-')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('ends_at')
                            ->placeholder('-')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
