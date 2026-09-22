<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transfers\TransferResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransfers extends ListRecords
{
    protected static string $resource = TransferResource::class;

    /**
     * Get the resource's header actions.
     *
     * @return array<\Filament\Actions\ActionGroup>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\CreateAction::make(),
            ]),
        ];
    }
}
