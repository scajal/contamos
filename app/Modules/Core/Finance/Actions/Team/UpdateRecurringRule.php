<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsMovementCategory;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateRecurringRule
{
    use GuardsMovementCategory;

    /**
     * The team that owns the rule to update.
     */
    public ?Team $team = null;

    /**
     * Set the team that owns the rule to update.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Update the rule's template and schedule. The change applies to
     * future occurrences only: the rule's next due date is derived
     * from the last one it generated, so history stays as it was.
     * Any failure is cleared, since the edit may be its fix.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $tagIds
     */
    public function using(RecurringRule $rule, array $attributes, array $tagIds = []): RecurringRule
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A recurring rule cannot be updated without a team.');
        }

        // The category is held against the type the rule is being
        // saved with, so a switched type never keeps the category
        // of the type it left behind.
        $this->guardCategory($attributes, $rule->type);

        return TeamActivity::within(fn (): RecurringRule => DB::transaction(function () use ($rule, $attributes, $tagIds): RecurringRule {
            // Get the currency for the rule, whose scale the amount is held at.
            /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
            $currency = Currency::query()
                ->whereKey($attributes['currency_id'])
                ->first();

            $rule->update([
                ...$attributes,
                'decimal_places' => $currency->decimal_places,
            ]);

            $rule->syncTags($tagIds);
            $rule->clearFailure();

            return $rule;
        }));
    }
}
