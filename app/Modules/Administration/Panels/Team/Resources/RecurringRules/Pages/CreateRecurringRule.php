<?php

declare(strict_types=1);

namespace App\Modules\Administration\Panels\Team\Resources\RecurringRules\Pages;

use App\Modules\Administration\Panels\Team\Resources\RecurringRules\RecurringRuleResource;
use App\Modules\Core\Finance\Actions\Team\CreateRecurringRule as CreateRecurringRuleAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRecurringRule extends CreateRecord
{
    protected static string $resource = RecurringRuleResource::class;

    protected static bool $canCreateAnother = false;

    protected CreateRecurringRuleAction $createRecurringRule;

    /**
     * Resolve the action that opens the rule.
     */
    public function boot(CreateRecurringRuleAction $createRecurringRule): void
    {
        $this->createRecurringRule = $createRecurringRule;
    }

    /**
     * Open the rule through the domain action, then attach the tags
     * its occurrences will carry.
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

        $rule = $this->createRecurringRule
            ->for($team)
            ->by($user)
            ->using(RecurringRuleResource::attributes($data));

        /** @var array<int, string> $tagIds */
        $tagIds = $data['tag_ids'] ?? [];

        $rule->attachTags($tagIds);

        return $rule;
    }
}
