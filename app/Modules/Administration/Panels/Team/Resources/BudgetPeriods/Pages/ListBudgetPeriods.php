<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages;

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBudgetPeriods extends ListRecords
{
    protected static string $resource = BudgetPeriodResource::class;

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
