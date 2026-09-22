<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\Pages;

use App\Modules\Administration\Panels\Team\Resources\BudgetPeriods\BudgetPeriodResource;
use Filament\Resources\Pages\ListRecords;

class ListBudgetPeriods extends ListRecords
{
    protected static string $resource = BudgetPeriodResource::class;
}
