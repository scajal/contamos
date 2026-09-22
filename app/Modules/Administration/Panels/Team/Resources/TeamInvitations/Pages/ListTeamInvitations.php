<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\TeamInvitations\Pages;

use App\Modules\Administration\Panels\Team\Resources\TeamInvitations\TeamInvitationResource;
use Filament\Resources\Pages\ListRecords;

class ListTeamInvitations extends ListRecords
{
    protected static string $resource = TeamInvitationResource::class;
}
