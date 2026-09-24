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
     * @return array<int, \Filament\Tables\Columns\TextColumn>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('description')
                // The subject may be gone, so it is named by the type
                // the entry recorded rather than the loaded model.
                ->formatStateUsing(fn (Activity $record): string => ($record->subject_type !== null ? class_basename($record->subject_type).' ' : '').$record->description),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime(),
            Tables\Columns\TextColumn::make('causer.email')
                ->label(trans('Caused by'))
                ->placeholder('-'),
        ];
    }
}
