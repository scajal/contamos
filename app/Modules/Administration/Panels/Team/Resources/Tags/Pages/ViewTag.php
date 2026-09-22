<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\Pages;

use App\Modules\Administration\Panels\Team\Resources\Tags\TagResource;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;

class ViewTag extends ViewRecord
{
    protected static string $resource = TagResource::class;

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
