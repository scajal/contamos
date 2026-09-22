<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Memberships\Pages;

use App\Modules\Administration\Panels\Team\Resources\Memberships\MembershipResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberships extends ListRecords
{
    protected static string $resource = MembershipResource::class;
}
