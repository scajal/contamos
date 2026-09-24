<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\Tables;

use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class TagsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
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
            Tables\Columns\TextColumn::make('name')
                ->weight(FontWeight::Medium)
                ->searchable(),
            Tables\Columns\TextColumn::make('is_active')
                ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Inactive'))
                ->color(fn (bool $state): array => $state ? Color::Green : Color::Red)
                ->badge(),
        ];
    }
}
