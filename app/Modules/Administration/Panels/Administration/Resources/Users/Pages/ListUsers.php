<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Administration\Resources\Users\Pages;

use App\Modules\Administration\Panels\Administration\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Get the resource's header actions.
     *
     * @return array<\Filament\Actions\Action>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [];
    }
}
