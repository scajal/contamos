<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Activities\Pages;

use App\Modules\Administration\Panels\Team\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListActivities extends ListRecords
{
    protected static string $resource = ActivityResource::class;
}
