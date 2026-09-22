<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Accounts\Pages;

use App\Modules\Administration\Panels\Team\Resources\Accounts\AccountResource;
use App\Modules\Core\Finance\Models\Team\Account;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class ViewAccount extends ViewRecord
{
    protected static string $resource = AccountResource::class;

    /**
     * Get the header actions.
     *
     * @return array<int, \Filament\Actions\ActionGroup>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    Actions\EditAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (Account $record): bool => $record->activate())
                        ->visible(fn (Account $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (Account $record): bool => $record->deactivate())
                        ->visible(fn (Account $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }
}
