<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers;

use App\Modules\Core\Identity\Enums\Team\Role;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class MembersRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'teams';

    protected static string $relationship = 'users';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Members');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns($this->getColumns())
            ->headerActions($this->getHeaderActions())
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
                    Tables\Columns\TextColumn::make('email')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('membership.role')
                    ->label(trans('Role'))
                    ->badge(),
            ]),
        ];
    }

    /**
     * Get the header actions for the relation manager's table.
     *
     * @return array<\Filament\Actions\Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\AttachAction::make()
                ->modalWidth(Width::Medium)
                ->preloadRecordSelect()
                ->slideOver()
                ->schema(fn (Actions\AttachAction $action): array => [
                    $action->getRecordSelect(),
                    Forms\Components\Select::make('role')
                        ->placeholder(trans('Select a role'))
                        ->default(Role::Member)
                        ->options(Role::class)
                        ->enum(Role::class)
                        ->required(),
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
                Actions\EditAction::make()
                    ->modalWidth(Width::Medium)
                    ->slideOver()
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->placeholder(trans('Select a role'))
                            ->options(Role::class)
                            ->enum(Role::class)
                            ->required(),
                    ]),
                Actions\DetachAction::make(),
            ]),
        ];
    }
}
