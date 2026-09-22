<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Customers\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Customers\CustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;
}
