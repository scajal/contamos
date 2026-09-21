<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsBudgetPeriodOverlap;
use App\Modules\Core\Finance\Actions\Team\Concerns\ReadsBudgetMonths;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Support\Helpers\TeamActivity;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Support\Facades\DB;

class CreateBudgetPeriod
{
    use GuardsBudgetPeriodOverlap;
    use ReadsBudgetMonths;

    /**
     * The tag the period will be created on.
     */
    public ?Tag $tag = null;

    /**
     * The user creating the period.
     */
    public ?User $user = null;

    /**
     * Set the user creating the period.
     */
    public function by(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set the tag the period will be created on.
     */
    public function for(Tag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Open a budget period on the tag with the provided months
     * and amount, in the provided currency.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): BudgetPeriod
    {
        // If the tag is not set, throw an exception.
        if (! $this->tag instanceof Tag) {
            throw new Exception('A budget period cannot be created without a tag.');
        }

        $tag = $this->tag;
        $currencyKey = new BudgetPeriod()->currency()->getForeignKeyName();

        $currencyId = $this->currencyId($attributes[$currencyKey] ?? null);
        $from = $this->month($attributes['from_month'] ?? null);
        $until = $this->month($attributes['until_month'] ?? null);

        // A stretch without a first month has nowhere to start.
        if (! $from instanceof CarbonImmutable) {
            throw new Exception('A budget period cannot be created without a first month.');
        }

        // The months must be free of any other period on the
        // same tag and currency.
        $this->guardAgainstOverlap($tag, $currencyId, $from, $until);

        // Everything the creation writes reads as one entry in
        // the team's activity log.
        return TeamActivity::within(fn (): BudgetPeriod => DB::transaction(function () use ($tag, $attributes, $currencyId, $from, $until): BudgetPeriod {
            /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod $period */
            $period = $tag->budgetPeriods()->create([
                'amount' => $attributes['amount'],
                new BudgetPeriod()->currency()->getForeignKeyName() => $currencyId,
                'from_month' => $from,
                'until_month' => $until,
            ]);

            return $period;
        }));
    }
}
