<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsBudgetPeriodOverlap;
use App\Modules\Core\Finance\Actions\Team\Concerns\ReadsBudgetMonths;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateBudgetPeriod
{
    use GuardsBudgetPeriodOverlap;
    use ReadsBudgetMonths;

    /**
     * The team the period belongs to.
     */
    public ?Team $team = null;

    /**
     * The user changing the period.
     */
    public ?User $user = null;

    /**
     * Set the user changing the period.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the team the period belongs to.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Change the period's amount, currency or months, keeping
     * the target's budget free of overlapping stretches. What
     * the period follows is fixed: a budget moved to another
     * target is a new budget, not an edit.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(BudgetPeriod $period, array $attributes): BudgetPeriod
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A budget period cannot be updated without a team.');
        }

        $team = $this->team;
        $target = $this->guardTarget($team, $period->target());
        $currencyKey = $period->currency()->getForeignKeyName();

        $currencyId = $this->currencyId($attributes[$currencyKey] ?? $period->getAttribute($currencyKey));

        $from = array_key_exists('from_month', $attributes)
            ? $this->month($attributes['from_month']) ?? $period->from_month
            : $period->from_month;

        $until = array_key_exists('until_month', $attributes)
            ? $this->month($attributes['until_month'])
            : $period->until_month;

        // The new months must stay clear of the target's other
        // periods in the same currency.
        $this->guardAgainstOverlap($team, $target, $currencyId, $from, $until, $period);

        // Everything the change writes reads as one entry in
        // the team's activity log.
        return TeamActivity::within(fn (): BudgetPeriod => DB::transaction(function () use ($period, $attributes, $currencyKey, $currencyId, $from, $until): BudgetPeriod {
            $period->update([
                'amount' => $attributes['amount'] ?? $period->amount,
                $currencyKey => $currencyId,
                'from_month' => $from,
                'until_month' => $until,
            ]);

            return $period;
        }));
    }
}
