<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\Tags\Pages;

use App\Modules\Administration\Panels\Team\Resources\Tags\TagResource;
use App\Modules\Core\Finance\Actions\Team\UpdateTag as UpdateTagAction;
use App\Modules\Core\Finance\Models\Team\Tag;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected UpdateTagAction $updateTag;

    /**
     * Resolve the action that updates the tag.
     */
    public function boot(UpdateTagAction $updateTag): void
    {
        $this->updateTag = $updateTag;
    }

    /**
     * Get the header actions.
     *
     * @return array<int, \Filament\Actions\ActionGroup>
     */
    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                ])->dropdown(false),
                Actions\ActionGroup::make([
                    Actions\Action::make('activate')
                        ->action(fn (Tag $record): bool => $record->activate())
                        ->visible(fn (Tag $record): bool => ! $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::Check)
                        ->color(Color::Green),
                    Actions\Action::make('deactivate')
                        ->action(fn (Tag $record): bool => $record->deactivate())
                        ->visible(fn (Tag $record): bool => $record->is_active)
                        ->successNotificationTitle(trans('Saved'))
                        ->requiresConfirmation()
                        ->icon(Heroicon::XMark)
                        ->color(Color::Red),
                ])->dropdown(false),
            ]),
        ];
    }

    /**
     * Update the tag through the domain action, leaving the budget
     * periods it holds untouched.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Tag $record */
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var \App\Modules\Core\Identity\Models\User $user */
        $user = Filament::auth()->user();

        return $this->updateTag
            ->for($team)
            ->by($user)
            ->using($record, ['name' => $data['name']]);
    }
}
