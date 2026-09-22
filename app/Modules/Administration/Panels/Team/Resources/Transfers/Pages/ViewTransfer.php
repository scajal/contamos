<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transfers\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transfers\TransferResource;
use App\Modules\Core\Finance\Actions\Team\DeleteTransfer as DeleteTransferAction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;

class ViewTransfer extends ViewRecord
{
    protected static string $resource = TransferResource::class;

    protected DeleteTransferAction $deleteTransfer;

    /**
     * Resolve the action that takes the transfer back.
     */
    public function boot(DeleteTransferAction $deleteTransfer): void
    {
        $this->deleteTransfer = $deleteTransfer;
    }

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
                Actions\DeleteAction::make()
                    ->using(function (Transfer $record): bool {
                        /** @var \App\Modules\Core\Identity\Models\Team $team */
                        $team = Filament::getTenant();

                        $this->deleteTransfer->for($team)->using($record);

                        return true;
                    }),
            ]),
        ];
    }
}
