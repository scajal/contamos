<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Activities\Schemas;

use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class ActivityInfolist
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
                        Infolists\Components\TextEntry::make('description'),
                        Infolists\Components\TextEntry::make('event')
                            ->placeholder('-')
                            ->badge(),
                        Infolists\Components\TextEntry::make('subject_type')
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('causer.email')
                            ->label(trans('Caused by'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\KeyValueEntry::make('properties')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
