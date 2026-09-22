<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Schemas;

use Filament\Infolists;
use Filament\Schemas;
use Filament\Schemas\Schema;

class AccountInfolist
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
                        Infolists\Components\TextEntry::make('type')
                            ->badge(),
                        Infolists\Components\TextEntry::make('is_active')
                            ->label(trans('State'))
                            ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Inactive'))
                            ->badge(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                    ]),
            ]);
    }
}
