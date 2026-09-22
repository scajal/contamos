<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transactions\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    protected static string $resource = TransactionResource::class;

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
