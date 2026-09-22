<?php

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsMovementCategory;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateRecurringRule
{
    use GuardsMovementCategory;

    /**
     * The team for which the rule will be created.
     */
    public ?Team $team = null;

    /**
     * The user that created the rule.
     */
    public ?User $user = null;

    /**
     * Set the user that created the rule.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team for which the rule will be created.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Create a new recurring rule for a team with the provided
     * template and schedule.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): RecurringRule
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A recurring rule cannot be created without a team.');
        }

        // The rule is the template of a transaction, so it holds
        // the same category invariant.
        $this->guardCategory($attributes);

        return TeamActivity::within(fn (): RecurringRule => DB::transaction(function () use ($attributes): RecurringRule {
            // Get the currency for the rule.
            /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
            $currency = Currency::query()
                ->whereKey($attributes['currency_id'])
                ->first();

            // Create the recurring rule for the team.
            /** @var \App\Modules\Core\Finance\Models\Team\RecurringRule $rule */
            $rule = $this->team?->recurringRules()->create([
                ...$attributes,
                'decimal_places' => $currency->decimal_places,
            ]);

            // If the user is provided, associate it as the creator.
            if ($this->user instanceof User) {
                $rule->user()->associate($this->user)->save();
            }

            return $rule;
        }));
    }
}
