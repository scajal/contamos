<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Actions\Team\Concerns;

use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use Carbon\CarbonImmutable;

/**
 * Changes what a tag is allowed to spend from this month on,
 * without rewriting the months already behind it. This is what
 * the quick amount on the tag form writes, on both the create
 * and the update path.
 */
trait ReplacesRunningBudgetPeriod
{
    /**
     * Set the tag's running amount for the currency from the
     * current month on. A blank amount stops the budget at
     * the end of last month. The period in force for past
     * months is left as it was, so history keeps the
     * amount that applied to it.
     */
    protected function replaceRunningPeriod(Tag $tag, string $currencyId, ?string $amount): ?BudgetPeriod
    {
        $month = CarbonImmutable::now()->startOfMonth();
        $running = $this->runningPeriod($tag, $currencyId, $month);

        // The amount was cleared, so the running stretch stops
        // at the end of last month. A stretch that only ever
        // covered this month is removed outright, since it
        // cannot close before it opens. Nothing running and
        // nothing to set simply leaves the tag without a
        // budget in this currency.
        if (blank($amount)) {
            if ($running instanceof BudgetPeriod) {
                $this->stopRunningPeriod($running, $month);
            }

            return null;
        }

        // Nothing is running, so a fresh open-ended stretch
        // starts this month.
        if (! $running instanceof BudgetPeriod) {
            return $this->openRunningPeriod($tag, $currencyId, $amount, $month);
        }

        // The amount has not moved, so there is nothing to
        // record and no stretch to split.
        if (is_numeric($amount) && bccomp((string) $running->amount, $amount, 18) === 0) {
            return $running;
        }

        // The stretch only ever covered months from this one
        // on, so there is no history to keep and the amount
        // is changed in place.
        if ($running->from_month->greaterThanOrEqualTo($month)) {
            $running->update(['amount' => $amount]);

            return $running;
        }

        // Past months keep the amount that was in force, so the
        // running stretch is closed at the end of last month
        // and a new one opens with the new amount.
        $running->update(['until_month' => $month->subMonth()]);

        return $this->openRunningPeriod($tag, $currencyId, $amount, $month);
    }

    /**
     * The tag's period in force this month for the currency,
     * if it has one.
     */
    protected function runningPeriod(Tag $tag, string $currencyId, CarbonImmutable $month): ?BudgetPeriod
    {
        /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod|null $period */
        $period = $tag->budgetPeriods()
            ->where(new BudgetPeriod()->currency()->getForeignKeyName(), $currencyId)
            ->covering($month)
            ->first();

        return $period;
    }

    /**
     * Open an unbounded stretch on the tag from the
     * given month.
     */
    protected function openRunningPeriod(Tag $tag, string $currencyId, string $amount, CarbonImmutable $month): BudgetPeriod
    {
        $period = new BudgetPeriod;

        /** @var \App\Modules\Core\Finance\Models\Team\BudgetPeriod $created */
        $created = $tag->budgetPeriods()->create([
            'amount' => $amount,
            'category' => null,
            $period->currency()->getForeignKeyName() => $currencyId,
            'from_month' => $month,
            // The quick amount on the tag form budgets a tag,
            // and the period carries the tag's own team.
            $period->team()->getForeignKeyName() => $tag->getAttribute($tag->team()->getForeignKeyName()),
            'until_month' => null,
        ]);

        return $created;
    }

    /**
     * Close the running stretch at the end of last month, or
     * drop it when it never covered a month before this one.
     */
    protected function stopRunningPeriod(BudgetPeriod $running, CarbonImmutable $month): void
    {
        if ($running->from_month->greaterThanOrEqualTo($month)) {
            $running->delete();

            return;
        }

        $running->update(['until_month' => $month->subMonth()]);
    }
}
