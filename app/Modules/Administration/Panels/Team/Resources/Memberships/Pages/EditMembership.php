<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Memberships\Pages;

use App\Modules\Administration\Panels\Team\Resources\Memberships\MembershipResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMembership extends EditRecord
{
    protected static string $resource = MembershipResource::class;

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
                Actions\DeleteAction::make(),
            ]),
        ];
    }
}
