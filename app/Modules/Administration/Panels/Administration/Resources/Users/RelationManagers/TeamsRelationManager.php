<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users\RelationManagers;

use App\Modules\Administration\Panels\Administration\Resources\Teams\TeamResource;
use App\Modules\Core\Identity\Models\Team;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TeamsRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'users';

    protected static string $relationship = 'teams';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Teams');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns($this->getColumns())
            ->recordActions($this->getRecordActions());
    }

    /**
     * Get the columns for the relation manager's table.
     *
     * @return array<\Filament\Tables\Columns\Layout\Split>
     */
    protected function getColumns(): array
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
                Tables\Columns\TextColumn::make('membership.role')
                    ->label(trans('Role'))
                    ->badge(),
            ]),
        ];
    }

    /**
     * Get the row actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\ActionGroup>
     */
    protected function getRecordActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('view')
                    ->icon(Heroicon::OutlinedEye)
                    ->url(fn (Team $record): string => TeamResource::getUrl('view', ['record' => $record])),
                Actions\Action::make('view-panel')
                    ->label(trans('Team panel'))
                    ->icon(Heroicon::OutlinedRectangleGroup)
                    ->url(fn (Team $record): ?string => Filament::getPanel('team')->getUrl($record)),
            ]),
        ];
    }
}
