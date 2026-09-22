<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Memberships\Tables;

use App\Modules\Core\Identity\Enums\Team\Role;
use Filament\Actions;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class MembershipsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::getColumns())
            ->filters(self::getFilters())
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
                    Tables\Columns\TextColumn::make('user.name')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('user.email')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
            ]),
        ];
    }

    /**
     * Get the table filters.
     *
     * @return array<int, \Filament\Tables\Filters\BaseFilter>
     */
    protected static function getFilters(): array
    {
        return [
            Tables\Filters\SelectFilter::make('role')
                ->options(Role::class),
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
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]),
        ];
    }
}
