<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\RelationManagers;

use App\Modules\Core\Identity\Models\Team\TeamInvitation;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvitationsRelationManager extends RelationManager
{
    protected static ?string $inverseRelationship = 'team';

    protected static string $relationship = 'invitations';

    /**
     * Get the title of the relation manager.
     */
    #[\Override]
    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('Invitations');
    }

    /**
     * Configure the relation manager's table.
     */
    #[\Override]
    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->defaultSort('created_at', 'desc')
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
            Tables\Columns\TextColumn::make('email')
                ->searchable(isIndividual: true)
                ->weight(FontWeight::Medium),
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
                Actions\Action::make('renew')
                    ->action(fn (TeamInvitation $record): TeamInvitation => $record->renew())
                    ->visible(fn (TeamInvitation $record): bool => ! $record->isAccepted())
                    ->successNotificationTitle(trans('Saved'))
                    ->icon(LucideIcon::RefreshCcw)
                    ->requiresConfirmation()
                    ->label(trans('Resend')),
                Actions\DeleteAction::make(),
            ]),
        ];
    }
}
