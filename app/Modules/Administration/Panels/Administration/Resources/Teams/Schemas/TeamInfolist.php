<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Schemas;

use App\Modules\Core\Identity\Models\Team;
use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class TeamInfolist
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
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('slug'),
                        Infolists\Components\TextEntry::make('plan')
                            ->state(fn (Team $record): string => $record->plan()->getLabel())
                            ->badge(),
                        Infolists\Components\TextEntry::make('owner.email')
                            ->label(trans('Owner'))
                            ->placeholder('-'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
