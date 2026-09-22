<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Tables;

use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Support\Helpers\Amount;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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
                    Tables\Columns\TextColumn::make('description')
                        ->weight(FontWeight::Medium)
                        ->placeholder('-')
                        ->searchable(),
                    Tables\Columns\TextColumn::make('account.name')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('frequency')
                    ->badge(),
                Tables\Columns\TextColumn::make('amount')
                    ->formatStateUsing(fn (RecurringRule $record): string => new Amount()->format((string) $record->amount, $record->currency)),
                Tables\Columns\TextColumn::make('is_active')
                    ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Paused'))
                    ->color(fn (bool $state): array => $state ? Color::Green : Color::Amber)
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
            Tables\Filters\SelectFilter::make('frequency')
                ->options(Frequency::class),
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
                    Actions\EditAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('resume')
                        ->action(fn (RecurringRule $record): bool => $record->activate())
                        ->visible(fn (RecurringRule $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Play)
                        ->color(Color::Green),
                    Actions\Action::make('pause')
                        ->action(fn (RecurringRule $record): bool => $record->deactivate())
                        ->visible(fn (RecurringRule $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Pause)
                        ->color(Color::Amber),
                ])->dropdown(false),
            ]),
        ];
    }
}
