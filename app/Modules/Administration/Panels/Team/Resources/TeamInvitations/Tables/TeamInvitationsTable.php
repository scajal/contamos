<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\TeamInvitations\Tables;

use App\Modules\Core\Identity\Enums\Team\Role;
use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use Filament\Actions;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class TeamInvitationsTable
{
    /**
     * Configure the resource's table.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
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
                    Tables\Columns\TextColumn::make('email')
                        ->weight(FontWeight::Medium)
                        ->searchable(),
                    Tables\Columns\TextColumn::make('inviter.email')
                        ->label(trans('Invited by'))
                        ->placeholder('-'),
                ]),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->state(fn (TeamInvitation $record): string => match (true) {
                        $record->isAccepted() => trans('Accepted'),
                        $record->isExpired() => trans('Expired'),
                        default => trans('Pending'),
                    })
                    ->color(fn (TeamInvitation $record): array => match (true) {
                        $record->isAccepted() => Color::Green,
                        $record->isExpired() => Color::Red,
                        default => Color::Amber,
                    })
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
                Actions\Action::make('renew')
                    ->action(fn (TeamInvitation $record): TeamInvitation => $record->renew())
                    ->visible(fn (TeamInvitation $record): bool => ! $record->isAccepted())
                    ->successNotificationTitle(trans('Saved'))
                    ->requiresConfirmation(),
                Actions\DeleteAction::make(),
            ]),
        ];
    }
}
