<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages;

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\RecurringRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRecurringRules extends ListRecords
{
    protected static string $resource = RecurringRuleResource::class;

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
