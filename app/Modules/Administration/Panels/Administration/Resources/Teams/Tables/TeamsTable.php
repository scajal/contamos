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
            Tables\Columns\TextColumn::make('name')
                ->weight(FontWeight::Medium)
                ->searchable(isIndividual: true),
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
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\DeleteAction::make(),
                ])->dropdown(false),
            ]),
        ];
    }
}
