<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Tables;

use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class RecurringRulesTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on', 'desc')
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
            Tables\Columns\TextColumn::make('description')
                ->weight(FontWeight::Medium)
                ->placeholder('-')
                ->searchable(),
            Tables\Columns\TextColumn::make('account.name')
                ->searchable(),
            Tables\Columns\TextColumn::make('category')
                ->placeholder('-')
                ->badge(),
            Tables\Columns\TextColumn::make('frequency')
                ->badge(),
            Tables\Columns\TextColumn::make('is_active')
                ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Paused'))
                ->color(fn (bool $state): array => $state ? Color::Green : Color::Amber)
                ->badge(),
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
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                ])->dropdown(false),
            ]),
        ];
    }
}
