<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Tables;

use App\Modules\Core\Identity\Models\Team;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class TeamsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
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
                    Tables\Columns\TextColumn::make('name')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('slug')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('users_count')
                    ->label(trans('Members'))
                    ->counts('users')
                    ->badge(),
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
                Actions\ActionGroup::make([
                    Actions\Action::make('view-panel')
                        ->label(trans('Team panel'))
                        ->icon(Heroicon::OutlinedRectangleGroup)
                        ->url(fn (Team $record): ?string => Filament::getPanel('team')->getUrl($record)),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\EditAction::make(),
                    Actions\DeleteAction::make(),
                ])->dropdown(false),
            ]),
        ];
    }
}
