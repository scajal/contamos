<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Transactions\Pages;

use App\Modules\Administration\Panels\Team\Resources\Transactions\TransactionResource;
use App\Modules\Core\Finance\Models\Team\Transaction;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

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
                Actions\EditAction::make()
                    ->visible(fn (Transaction $record): bool => ! $record->transfer()->exists()),
            ]),
        ];
    }
}
