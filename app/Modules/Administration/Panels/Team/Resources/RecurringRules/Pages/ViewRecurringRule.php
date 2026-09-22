<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages;

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\RecurringRuleResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRecurringRule extends ViewRecord
{
    protected static string $resource = RecurringRuleResource::class;
}
