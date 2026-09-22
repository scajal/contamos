<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\Pages;

use App\Modules\Administration\Panels\Team\Resources\Tags\TagResource;
use App\Modules\Core\Finance\Actions\Team\CreateTag as CreateTagAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateTagAction $createTag;

    /**
     * Resolve the action that creates the tag.
     */
    public function boot(CreateTagAction $createTag): void
    {
        $this->createTag = $createTag;
    }

    /**
     * Create the tag through the domain action, so it reads as one
     * entry in the team's activity log. Budget periods are opened
     * afterwards, from the tag's own page.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordCreation(array $data): Model
    {
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        return $this->createTag
            ->for($team)
            ->by($user)
            ->using(['name' => $data['name']]);
    }
}
