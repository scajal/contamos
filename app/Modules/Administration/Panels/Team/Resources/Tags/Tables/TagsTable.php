<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\Tables;

use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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
                    Tables\Columns\TextColumn::make('name')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('budget_periods_count')
                        ->label(trans('Budgets'))
                        ->counts('budgetPeriods'),
                ]),
                Tables\Columns\TextColumn::make('is_active')
                    ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Inactive'))
                    ->color(fn (bool $state): array => $state ? Color::Green : Color::Red)
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
            Tables\Filters\TernaryFilter::make('is_active')
                ->label(trans('Active')),
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
                    Actions\EditAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (Tag $record): bool => $record->activate())
                        ->visible(fn (Tag $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (Tag $record): bool => $record->deactivate())
                        ->visible(fn (Tag $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }
}
