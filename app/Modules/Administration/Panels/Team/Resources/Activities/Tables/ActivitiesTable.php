<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Activities\Tables;

use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                        ->searchable(),
                    Tables\Columns\TextColumn::make('created_at')
                        ->dateTime(),
                ]),
                Tables\Columns\TextColumn::make('event')
                    ->placeholder('-')
                    ->badge(),
                Tables\Columns\TextColumn::make('causer.email')
                    ->label(trans('Caused by'))
                    ->placeholder('-'),
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
