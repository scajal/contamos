<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Currencies\CurrencyResource;
use App\Modules\Core\Catalog\Models\Currency;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class EditCurrency extends EditRecord
{
    protected static string $resource = CurrencyResource::class;

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
                    Actions\ViewAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (Currency $record): bool => $record->update(['is_active' => true]))
                        ->visible(fn (Currency $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (Currency $record): bool => $record->update(['is_active' => false]))
                        ->visible(fn (Currency $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }
}
