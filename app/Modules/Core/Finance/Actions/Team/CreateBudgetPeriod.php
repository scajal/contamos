<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team;

use App\Modules\Core\Finance\Actions\Team\Concerns\GuardsBudgetPeriodOverlap;
use App\Modules\Core\Finance\Actions\Team\Concerns\ReadsBudgetMonths;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
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
     * The team the period will be created for.
     */
    public ?Team $team = null;

    /**
     * The category or tag the period will follow.
     */
    public Category|Tag|null $target = null;

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
     * Set the team the period will be created for.
     */
    public function for(Team $team): self
    {
        $this->team = $team;

        return $this;
    }

    /**
     * Set the category or tag the period will follow. Nothing
     * to follow is refused when the period is written, not
     * when it is named.
     */
    public function targeting(Category|Tag|null $target): self
    {
        $this->target = $target;

        return $this;
    }

    /**
     * Open a budget period on the target with the provided
     * months and amount, in the provided currency.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function using(array $attributes): BudgetPeriod
    {
        // If the team is not set, throw an exception.
        if (! $this->team instanceof Team) {
            throw new Exception('A budget period cannot be created without a team.');
        }

        $team = $this->team;
        $target = $this->guardTarget($team, $this->target);
        $currencyKey = new BudgetPeriod()->currency()->getForeignKeyName();

        $currencyId = $this->currencyId($attributes[$currencyKey] ?? null);
        $from = $this->month($attributes['from_month'] ?? null);
        $until = $this->month($attributes['until_month'] ?? null);

        // A stretch without a first month has nowhere to start.
        if (! $from instanceof CarbonImmutable) {
            throw new Exception('A budget period cannot be created without a first month.');
        }

        // The months must be free of any other period on the
        // same target and currency.
        $this->guardAgainstOverlap($team, $target, $currencyId, $from, $until);

        // Everything the creation writes reads as one entry in
        // the team's activity log.
        return TeamActivity::within(fn (): BudgetPeriod => DB::transaction(function () use ($team, $target, $attributes, $currencyKey, $currencyId, $from, $until): BudgetPeriod {
            /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod $period */
            $period = $team->budgets()->create([
                ...$this->targetAttributes($target),
                'amount' => $attributes['amount'],
                $currencyKey => $currencyId,
                'from_month' => $from,
                'until_month' => $until,
            ]);

            return $period;
        }));
    }

    /**
     * Pick a stopped period's budget back up from the current
     * month, on the target, amount and currency it was
     * stopped at, with no last month.
     */
    public function resume(BudgetPeriod $stopped): BudgetPeriod
    {
        $currencyKey = $stopped->currency()->getForeignKeyName();

        // Open a fresh unbounded stretch from this month, on
        // whatever the stopped one followed.
        return $this->targeting($stopped->target())->using([
            'amount' => (string) $stopped->amount,
            $currencyKey => $stopped->getAttribute($currencyKey),
            'from_month' => CarbonImmutable::now()->startOfMonth(),
            'until_month' => null,
        ]);
    }
}
