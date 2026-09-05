<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Currencies\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Currencies\CurrencyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrency extends CreateRecord
{
    protected static string $resource = CurrencyResource::class;

    protected static bool $canCreateAnother = false;
}
