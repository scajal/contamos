<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\Pages;

use App\Modules\Administration\Panels\Administration\Resources\BillingTransactions\BillingTransactionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBillingTransaction extends ViewRecord
{
    protected static string $resource = BillingTransactionResource::class;
}
