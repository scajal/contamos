<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users\Tables;

use App\Modules\Core\Identity\Models\User;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;

class UsersTable
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
     * @return array<int, \Filament\Tables\Columns\Layout\Split|\Filament\Tables\Columns\Layout\Stack>
     */
    protected static function getColumns(): array
    {
        return [
            Tables\Columns\Layout\Split::make([
                Tables\Columns\ImageColumn::make('avatar')
                    ->grow(false)
                    ->circular(),
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\TextColumn::make('name')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('email')
                        ->searchable(),
                ]),
                Tables\Columns\TextColumn::make('is_active')
                    ->formatStateUsing(fn (bool $state): string => $state ? trans('Active') : trans('Inactive'))
                    ->color(fn (bool $state): array => $state ? Color::Green : Color::Red)
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
                    Actions\ViewAction::make(),
                    Actions\DeleteAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (User $record): bool => $record->update(['is_active' => true]))
                        ->visible(fn (User $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (User $record): bool => $record->update(['is_active' => false]))
                        ->visible(fn (User $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }
}
