<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages;

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\RecurringRuleResource;
use App\Modules\Core\Finance\Actions\Team\DeleteRecurringRule as DeleteRecurringRuleAction;
use App\Modules\Core\Finance\Actions\Team\UpdateRecurringRule as UpdateRecurringRuleAction;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRecurringRule extends EditRecord
{
    protected static string $resource = RecurringRuleResource::class;

    protected DeleteRecurringRuleAction $deleteRecurringRule;

    protected UpdateRecurringRuleAction $updateRecurringRule;

    /**
     * Resolve the actions that write the rule.
     */
    public function boot(DeleteRecurringRuleAction $deleteRecurringRule, UpdateRecurringRuleAction $updateRecurringRule): void
    {
        $this->deleteRecurringRule = $deleteRecurringRule;
        $this->updateRecurringRule = $updateRecurringRule;
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
                Actions\DeleteAction::make()
                    ->using(function (RecurringRule $record): bool {
                        /** @var \App\Modules\Core\Identity\Models\Team $team */
                        $team = Filament::getTenant();

                        $this->deleteRecurringRule->for($team)->using($record);

                        return true;
                    }),
            ]),
        ];
    }

    /**
     * Fill the tags the rule carries into the form.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var \App\Modules\Core\Finance\Models\Team\RecurringRule $rule */
        $rule = $this->getRecord();

        $data['tag_ids'] = $rule->tags()->pluck('tags.id')->all();

        return $data;
    }

    /**
     * Update the rule through the domain action, which re-reads the
     * schedule the next occurrence is generated from.
     *
     * @param  array<string, mixed>  $data
     */
    #[\Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Modules\Core\Finance\Models\Team\RecurringRule $record */
        /** @var \App\Modules\Core\Identity\Models\Team $team */
        $team = Filament::getTenant();

        /** @var array<int, string> $tagIds */
        $tagIds = $data['tag_ids'] ?? [];

        return $this->updateRecurringRule
            ->for($team)
            ->using($record, RecurringRuleResource::attributes($data), $tagIds);
    }
}
