<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsBudgetPeriodOverlap;
use App\Modules\Core\Finance\Actions\Team\Concerns\ReadsBudgetMonths;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Exception;
use Illuminate\Support\Facades\DB;

class UpdateBudgetPeriod
{
    use GuardsBudgetPeriodOverlap;
    use ReadsBudgetMonths;

    /**
     * The tag the period belongs to.
     */
    public ?Tag $tag = null;

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
     * Set the tag the period belongs to.
     */
    public function for(Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Change the period's amount, currency or months, keeping
     * the tag's budget free of overlapping stretches.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(BudgetPeriod $period, array $attributes): BudgetPeriod
    {
        // If the tag is not set, throw an exception.
        if (! $this->tag instanceof Tag) {
            throw new Exception('A budget period cannot be updated without a tag.');
        }

        $tag = $this->tag;
        $currencyKey = $period->currency()->getForeignKeyName();

        $currencyId = $this->currencyId($attributes[$currencyKey] ?? $period->getAttribute($currencyKey));

        $from = array_key_exists('from_month', $attributes)
            ? $this->month($attributes['from_month']) ?? $period->from_month
            : $period->from_month;

        $until = array_key_exists('until_month', $attributes)
            ? $this->month($attributes['until_month'])
            : $period->until_month;

        // The new months must stay clear of the tag's other
        // periods in the same currency.
        $this->guardAgainstOverlap($tag, $currencyId, $from, $until, $period);

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
