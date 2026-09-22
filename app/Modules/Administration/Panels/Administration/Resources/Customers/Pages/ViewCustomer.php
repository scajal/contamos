<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Customers\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;
}
