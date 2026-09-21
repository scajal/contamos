<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Reads the months, tag and currency a budget form submits, and
 * builds the rules that keep a tag's budget unambiguous.
 */
trait ResolvesBudgetInputs
{
    /**
     * The month format a budget form submits its
     * bounds in.
     */
    protected const string MONTH_FORMAT = 'Y-m';

    /**
     * Parse one of the submitted months, or null when it was
     * left out or is not a month at all.
     */
    protected function month(string $attribute): ?CarbonImmutable
    {
        $value = $this->input($attribute);

        if (! is_string($value) || blank($value)) {
            return null;
        }

        return CarbonImmutable::createFromFormat(self::MONTH_FORMAT, $value)?->startOfMonth();
    }

    /**
     * The tag the budget is being set on, taken from the route
     * on an edit and from the payload on a create.
     */
    protected function budgetTag(Team $team): ?Tag
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Tag|null $tag */
        $tag = $team->tags()
            ->whereKey($this->input(new BudgetPeriod()->tag()->getForeignKeyName()))
            ->first();

        return $tag;
    }

    /**
     * Restrict the currency to the ones the team holds on an
     * active account, since a budget in any other currency
     * could never show progress.
     */
    protected function heldCurrency(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            // Anything but a key has already been rejected by
            // the rules that run before this one.
            if (! is_string($value)) {
                return;
            }

            $held = $team->heldCurrencies()
                ->pluck(new Currency()->getKeyName())
                ->all();

            if (! in_array($value, $held, true)) {
                $fail(trans('Your team does not hold this currency on an active account.'));
            }
        };
    }

    /**
     * Reject months that run backwards or that another period
     * on the same tag and currency already covers.
     */
    protected function freeMonths(Team $team, ?BudgetPeriod $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team, $except): void {
            $from = $this->month('from_month');

            // Without a readable start there is nothing to
            // compare the rest against.
            if (! $from instanceof CarbonImmutable) {
                return;
            }

            $until = $this->month('until_month');

            // A stretch cannot close before it opens.
            if ($until instanceof CarbonImmutable && $until->lessThan($from)) {
                $fail(trans('The last month cannot be before the first month.'));

                return;
            }

            $tag = $except instanceof BudgetPeriod ? $except->tag : $this->budgetTag($team);

            // An unknown tag or currency is somebody else's
            // rule to report.
            if (! $tag instanceof Tag || ! is_string($currencyId = $this->input(new BudgetPeriod()->currency()->getForeignKeyName()))) {
                return;
            }

            $siblings = $tag->budgetPeriods()
                ->where(new BudgetPeriod()->currency()->getForeignKeyName(), $currencyId)
                ->when($except instanceof BudgetPeriod, fn ($query) => $query->whereKeyNot($except))
                ->get();

            foreach ($siblings as $sibling) {
                if ($sibling->overlaps($from, $until)) {
                    $fail(trans('This tag already has a budget covering those months in that currency.'));

                    return;
                }
            }
        };
    }

    /**
     * The rule that keeps a budget pointed at one of the
     * team's own tags.
     */
    protected function teamTag(Team $team): Exists
    {
        return Rule::exists(Tag::class, new Tag()->getKeyName())
            ->where(new Tag()->team()->getForeignKeyName(), $team->getKey());
    }
}
