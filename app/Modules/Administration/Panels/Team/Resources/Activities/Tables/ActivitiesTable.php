<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Activities\Tables;

use App\Modules\Core\Identity\Models\Team\Activity;
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
            ->columns(self::getColumns());
    }

    /**
     * Get the table columns.
     *
     * @return array<int, \Filament\Tables\Columns\Layout\Split>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('description')
                ->formatStateUsing(fn (Activity $record): string => class_basename($record->subject::class).' '.$record->description),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime(),
            Tables\Columns\TextColumn::make('causer.email')
                ->label(trans('Caused by'))
                ->placeholder('-'),
        ];
    }
}
