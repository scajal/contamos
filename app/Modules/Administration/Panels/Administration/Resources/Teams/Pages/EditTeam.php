<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Teams\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Teams\TeamResource;
use App\Modules\Core\Identity\Models\Team;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditTeam extends EditRecord
{
    protected static string $resource = TeamResource::class;

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
                    Actions\Action::make('view-panel')
                        ->label(trans('Team panel'))
                        ->icon(LucideIcon::Group)
                        ->url(fn (Team $record): ?string => Filament::getPanel('team')->getUrl($record)),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    Actions\DeleteAction::make(),
                ])->dropdown(false),
            ]),
        ];
    }
}
