<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Reports\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Reports\Team\Concerns\ReportsBudgetProgress;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The figures of a team's month: income, expenses and net per
 * currency against the month before, what moved between its
 * own accounts, the trend leading up to it and the breakdowns
 * by category and by tag with their budgets. The monthly page
 * and the MCP report read the same numbers from here.
 */
class MonthlyReport
{
    use ReportsBudgetProgress;

    /**
     * The format a month travels in, both in the URL and
     * in the figures.
     */
    public const string MONTH_FORMAT = 'Y-m';

    /**
     * The scale the signed money strings are computed with,
     * matching the decimal cast on the amount column.
     */
    protected const int SCALE = 18;

    /**
     * How many months the trend chart looks back, counting
     * the selected month as the last of them.
     */
    protected const int TREND_MONTHS = 6;

    /**
     * The newest month there is anything to report on: the
     * current one.
     */
    public function latestMonth(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfMonth();
    }

    /**
     * Whether a month falls before the oldest one the plan
     * opens, so it stays reachable but carries no figures.
     */
    public function isLocked(CarbonImmutable $month, CarbonImmutable $earliest): bool
    {
        return $month->lessThan($earliest);
    }

    /**
     * The oldest month the team may open: the month of its
     * first transaction, raised to the window the plan
     * allows.
     */
    public function earliestMonth(Team $team, CarbonImmutable $latest): CarbonImmutable
    {
        $first = $team->transactions()->min('occurred_at');

        // Without a transaction there is no history to reach
        // back to, so the current month is the whole range.
        $earliest = is_scalar($first)
            ? CarbonImmutable::parse((string) $first)->startOfMonth()
            : $latest;

        $limit = Feature::ViewHistory->limitOn($team->plan());

        // A limited plan counts the current month as the first
        // of the months it allows.
        if ($limit !== null) {
            $allowed = $latest->subMonths($limit - 1);

            $earliest = $earliest->lessThan($allowed) ? $allowed : $earliest;
        }

        return $earliest;
    }

    /**
     * Build one figure group per currency the team transacts
     * in, holding the month's totals, how each compares with
     * the month before and what was moved between accounts.
     *
     * @return array<int, array<string, mixed>>
     */
    public function figures(Team $team, CarbonImmutable $month, CarbonImmutable $earliest): array
    {
        $currencies = $this->currencies($team, $earliest);
        $tags = $this->tagsByCurrency($team, $month);
        $categories = $this->categoriesByCurrency($team, $month);

        // The trend ends on the selected month and stops at the
        // oldest month the plan opens, so it never half-shows a
        // month the team cannot reach.
        $trendMonths = $this->trendMonths($month, $earliest);

        // Every month of the trend is summed once and reused,
        // since the selected month and the one before it are
        // part of the same window.
        $summed = new Collection($trendMonths)
            ->mapWithKeys(fn (CarbonImmutable $trendMonth): array => [
                $trendMonth->format(self::MONTH_FORMAT) => $this->totalsByCurrency($team, $trendMonth),
            ]);

        $totals = $summed->get($month->format(self::MONTH_FORMAT), new Collection);

        // The month before the first one the plan opens is not
        // the team's to see, so nothing is compared against it.
        $previousMonth = $month->subMonth();
        $isComparable = $previousMonth->greaterThanOrEqualTo($earliest);
        $previousTotals = $summed->get($previousMonth->format(self::MONTH_FORMAT), new Collection);

        return $currencies
            ->map(function (Currency $currency) use ($totals, $previousTotals, $isComparable, $categories, $tags, $summed): array {
                $key = (string) $currency->getKey();
                $current = $totals->get($key, $this->emptyTotals());
                $previous = $isComparable
                    ? $previousTotals->get($key, $this->emptyTotals())
                    : null;

                return [
                    'categories' => $categories->get($key, []),
                    'currency' => $currency->only([$currency->getKeyName(), 'code', 'decimal_places', 'symbol']),
                    'expenses' => $this->total($current['expenses'], $previous === null ? null : $previous['expenses']),
                    'income' => $this->total($current['income'], $previous === null ? null : $previous['income']),
                    'net' => $this->total(
                        bcsub($current['income'], $current['expenses'], self::SCALE),
                        $previous === null ? null : bcsub($previous['income'], $previous['expenses'], self::SCALE),
                    ),
                    'tags' => $tags->get($key, []),
                    'transfers' => $this->transfers($current),
                    'trend' => $this->trend($summed, $key),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The months the trend covers: the six ending on the
     * selected month, cut short at the oldest month the
     * plan opens.
     *
     * @return array<int, \Carbon\CarbonImmutable>
     */
    protected function trendMonths(CarbonImmutable $month, CarbonImmutable $earliest): array
    {
        $start = $month->subMonths(self::TREND_MONTHS - 1);
        $start = $start->lessThan($earliest) ? $earliest : $start;

        $months = [];

        for ($cursor = $start; $cursor->lessThanOrEqualTo($month); $cursor = $cursor->addMonth()) {
            $months[] = $cursor;
        }

        return $months;
    }

    /**
     * The currency's income, expenses and net for each month of
     * the trend, where a month with nothing in it is kept at
     * zero so the axis stays regular.
     *
     * @param  \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string, moved_in: numeric-string, moved_out: numeric-string}>>  $summed
     * @return array<int, array<string, mixed>>
     */
    protected function trend(Collection $summed, string $currency): array
    {
        return $summed
            ->map(function (Collection $totals, string $month) use ($currency): array {
                $current = $totals->get($currency, $this->emptyTotals());

                return [
                    'expenses' => $current['expenses'],
                    'income' => $current['income'],
                    'month' => $month,
                    'net' => bcsub($current['income'], $current['expenses'], self::SCALE),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The currencies the team has transacted in on its active
     * accounts, within the months the plan opens, ordered the
     * way the dashboard balances are.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Catalog\Models\Currency>
     */
    protected function currencies(Team $team, CarbonImmutable $earliest): EloquentCollection
    {
        $transaction = new Transaction;

        return Currency::query()
            ->whereIn(
                new Currency()->getKeyName(),
                $team->transactions()
                    ->select($transaction->qualifyColumn($transaction->currency()->getForeignKeyName()))
                    ->whereRelation('account', new Account()->qualifyColumn('is_active'), true)
                    ->occurredFrom($earliest->toDateString()),
            )
            ->orderBy('code')
            ->get();
    }

    /**
     * Sum what came in, what went out and what was moved
     * between the team's own accounts in the month, keyed
     * by currency.
     *
     * @return \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string, moved_in: numeric-string, moved_out: numeric-string}>
     */
    protected function totalsByCurrency(Team $team, CarbonImmutable $month): Collection
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();

        // Income and expenses name their types rather than taking
        // everything that is not a transfer, so a type added later
        // cannot quietly land in the totals.
        $rows = $team->transactions()
            ->select($transaction->qualifyColumn($currencyKey))
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->selectRaw(...$this->sumOfType(TransactionType::TransferIn, 'moved_in'))
            ->selectRaw(...$this->sumOfType(TransactionType::TransferOut, 'moved_out'))
            ->whereRelation('account', new Account()->qualifyColumn('is_active'), true)
            ->occurredFrom($month->startOfMonth()->toDateString())
            ->occurredUntil($month->endOfMonth()->toDateString())
            ->groupBy($transaction->qualifyColumn($currencyKey))
            ->get();

        return $rows->mapWithKeys(fn (Transaction $row): array => [
            $this->text($row->getAttribute($currencyKey)) => [
                'expenses' => $this->decimal($row->getAttribute('expenses')),
                'income' => $this->decimal($row->getAttribute('income')),
                'moved_in' => $this->decimal($row->getAttribute('moved_in')),
                'moved_out' => $this->decimal($row->getAttribute('moved_out')),
            ],
        ]);
    }

    /**
     * Break the month down by tag for each currency. A
     * transaction counts in full under every tag it carries,
     * so the rows may add up to more than the totals.
     *
     * @return \Illuminate\Support\Collection<string, array<int, array<string, mixed>>>
     */
    protected function tagsByCurrency(Team $team, CarbonImmutable $month): Collection
    {
        // The breakdown is by tag, so the budgets following a
        // category are left out of it.
        $budgets = $this->budgetPeriodsFor($team, $month)
            ->filter(fn (BudgetPeriod $period): bool => $period->targetKind() === BudgetTargetKind::Tag);

        $rows = $this->taggedByCurrency($team, $month, $budgets);

        // Money carrying no tag at all still has to show up, so
        // it is summed apart and pinned below the tags.
        foreach ($this->untaggedByCurrency($team, $month) as $currency => $untagged) {
            $rows[$currency] = [...$rows[$currency] ?? [], $untagged];
        }

        return new Collection($rows);
    }

    /**
     * Break the month down by category for each currency. A
     * movement carries one category, so the rows add up to
     * the month's totals exactly.
     *
     * @return \Illuminate\Support\Collection<string, array<int, array<string, mixed>>>
     */
    protected function categoriesByCurrency(Team $team, CarbonImmutable $month): Collection
    {
        $periods = $this->budgetPeriodsFor($team, $month);

        // The breakdown is by category, so the budgets following
        // a tag are left out of it, except the sub-budgets that
        // sit under a category budget, which read under it.
        $budgets = $periods
            ->filter(fn (BudgetPeriod $period): bool => $period->targetKind() === BudgetTargetKind::Category);

        $parts = $periods
            ->filter(fn (BudgetPeriod $period): bool => $period->isNested())
            ->groupBy(fn (BudgetPeriod $period): string => $this->text($period->getAttribute($period->parent()->getForeignKeyName())));

        // A sub-budget only counts its tag within the category
        // it sits in, so its figures come from their own sums.
        $nested = $this->nestedTotals($team, $month)
            ->mapWithKeys(fn (Transaction $row): array => [$this->budgetKeyOf($row) => $this->totalsOf($row)]);

        $currencyKey = new Transaction()->currency()->getForeignKeyName();

        $rows = $this->categorizedTotals($team, $month)
            ->groupBy(fn (Transaction $row): string => $this->text($row->getAttribute($currencyKey)))
            ->mapWithKeys(fn (EloquentCollection $group, int|string $currency): array => [
                (string) $currency => $group
                    ->map(fn (Transaction $row): array => $this->categoryRow(
                        $this->category($row->getAttribute('category')),
                        $this->decimal($row->getAttribute('income')),
                        $this->decimal($row->getAttribute('expenses')),
                        $budgets->get($this->categoryBudgetKey(
                            $this->category($row->getAttribute('category'))->value,
                            $this->text($row->getAttribute($currencyKey)),
                        )),
                        $parts,
                        $nested,
                    ))
                    ->values()
                    ->all(),
            ])
            ->all();

        // A budget the month has not been spent against yet is
        // still something to report, so its category joins the
        // breakdown with a row of zeroes.
        $rows = $this->withQuietCategoryBudgets($rows, $budgets, $parts, $nested);

        return new Collection(array_map($this->orderedCategories(...), $rows));
    }

    /**
     * Add a zero row for every budgeted category the month has
     * no figures for, so the category's bar is shown from the
     * start of the month rather than appearing on first
     * spend.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $rows
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $budgets
     * @param  \Illuminate\Support\Collection<array-key, \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>>  $parts
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>  $nested
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function withQuietCategoryBudgets(array $rows, Collection $budgets, Collection $parts, Collection $nested): array
    {
        $currencyKey = new BudgetPeriod()->currency()->getForeignKeyName();

        foreach ($budgets as $period) {
            $currency = $this->text($period->getAttribute($currencyKey));
            $category = $this->category($period->getAttribute('category'));

            // The category already reported figures for the
            // currency, so its row carries the budget already.
            $isReported = new Collection($rows[$currency] ?? [])
                ->contains(fn (array $row): bool => $row['category'] === $category->value);

            if ($isReported) {
                continue;
            }

            $rows[$currency] = [
                ...$rows[$currency] ?? [],
                $this->categoryRow($category, '0', '0', $period, $parts, $nested),
            ];
        }

        return $rows;
    }

    /**
     * Order the rows of one currency the way the category list
     * reads, with Other last, so one month can be compared
     * with the next at a glance.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    protected function orderedCategories(array $rows): array
    {
        $order = array_flip(array_map(
            fn (Category $category): string => $category->value,
            Category::ordered(),
        ));

        return new Collection($rows)
            ->sortBy(fn (array $row): int => $order[$this->text($row['category'])] ?? count($order))
            ->values()
            ->all();
    }

    /**
     * One row of the category breakdown. A row with no budget
     * behind it carries a null block and no parts; a budgeted
     * one lists the sub-budgets sitting under its budget,
     * each with what the month made of it.
     *
     * @param  numeric-string  $income
     * @param  numeric-string  $expenses
     * @param  \Illuminate\Support\Collection<array-key, \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>>|null  $parts
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>|null  $nested
     * @return array<string, mixed>
     */
    protected function categoryRow(Category $category, string $income, string $expenses, ?BudgetPeriod $budget = null, ?Collection $parts = null, ?Collection $nested = null): array
    {
        return [
            'budget' => $budget instanceof BudgetPeriod
                ? $this->budgetProgress($budget, $income, $expenses)
                : null,
            'category' => $category->value,
            'expenses' => $expenses,
            'income' => $income,
            'net' => bcsub($income, $expenses, self::SCALE),
            'parts' => $budget instanceof BudgetPeriod
                ? $this->budgetParts($budget, $parts?->get($this->text($budget->getKey())) ?? new Collection, $nested ?? new Collection)
                : [],
        ];
    }

    /**
     * The sub-budgets sitting under a category budget, by tag
     * name, each with what the month made of it: only the
     * tag's movements filed under the category count.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $children
     * @param  \Illuminate\Support\Collection<string, array{expenses: numeric-string, income: numeric-string}>  $nested
     * @return array<int, array<string, mixed>>
     */
    protected function budgetParts(BudgetPeriod $budget, Collection $children, Collection $nested): array
    {
        return $children
            ->map(function (BudgetPeriod $child) use ($budget, $nested): array {
                // The child reads its figures within the parent's
                // category, which it only knows through it.
                $child->setRelation('parent', $budget);

                $activity = $nested->get($this->budgetKeyFor($child), ['expenses' => '0', 'income' => '0']);

                return [
                    'budget' => $this->budgetProgress($child, $activity['income'], $activity['expenses']),
                    'id' => $this->text($child->tag?->getKey()),
                    'name' => (string) $child->tag?->name,
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Read a cast category column as a case. The model casts
     * the column, so anything else only reads as Other, the
     * bucket the aggregate files uncategorized money under.
     */
    protected function category(mixed $value): Category
    {
        return $value instanceof Category ? $value : Category::Other;
    }

    /**
     * Sum the month's income and expenses per tag and currency,
     * biggest spender first, keeping tags the team has since
     * retired as long as they were used that month. A budgeted
     * tag that stayed quiet still gets a row, so its budget is
     * never missing from the month.
     *
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $budgets
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function taggedByCurrency(Team $team, CarbonImmutable $month, Collection $budgets): array
    {
        $currencyKey = new Transaction()->currency()->getForeignKeyName();

        $rows = $this->taggedTotals($team, $month)
            ->groupBy(fn (Transaction $row): string => $this->text($row->getAttribute($currencyKey)))
            ->mapWithKeys(fn (EloquentCollection $group, int|string $currency): array => [
                (string) $currency => $group
                    ->map(fn (Transaction $row): array => $this->tagRow(
                        $this->text($row->getAttribute('tag_id')),
                        $this->text($row->getAttribute('tag_name')),
                        $this->decimal($row->getAttribute('income')),
                        $this->decimal($row->getAttribute('expenses')),
                        $budgets->get($this->tagBudgetKey(
                            $this->text($row->getAttribute('tag_id')),
                            $this->text($row->getAttribute($currencyKey)),
                        )),
                    ))
                    ->values()
                    ->all(),
            ])
            ->all();

        // A budget the month has not been spent against yet is
        // still something to report, so its tag joins the
        // breakdown with a row of zeroes.
        $rows = $this->withQuietBudgets($rows, $budgets);

        return array_map(
            fn (array $group): array => new Collection($group)
                ->sort($this->byExpensesThenName(...))
                ->values()
                ->all(),
            $rows,
        );
    }

    /**
     * Add a zero row for every budgeted tag the month has no
     * figures for, so the tag's bar is shown from the start
     * of the month rather than appearing on first spend.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $rows
     * @param  \Illuminate\Support\Collection<string, \App\Modules\Core\Finance\Models\Team\BudgetPeriod>  $budgets
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function withQuietBudgets(array $rows, Collection $budgets): array
    {
        $currencyKey = new BudgetPeriod()->currency()->getForeignKeyName();

        foreach ($budgets as $period) {
            $currency = $this->text($period->getAttribute($currencyKey));
            $tagId = $this->text($period->getAttribute($period->tag()->getForeignKeyName()));

            // The tag already reported figures for the currency,
            // so its row carries the budget already.
            $isReported = new Collection($rows[$currency] ?? [])
                ->contains(fn (array $row): bool => $row['id'] === $tagId);

            if ($isReported) {
                continue;
            }

            $rows[$currency] = [
                ...$rows[$currency] ?? [],
                $this->tagRow($tagId, $this->text($period->tag?->name), '0', '0', $period),
            ];
        }

        return $rows;
    }

    /**
     * Sum the month's income and expenses that carry no tag
     * at all, per currency.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function untaggedByCurrency(Team $team, CarbonImmutable $month): array
    {
        $transaction = new Transaction;
        $currencyKey = $transaction->currency()->getForeignKeyName();

        $rows = $this->monthly($team, $month)
            ->select($transaction->qualifyColumn($currencyKey))
            ->selectRaw(...$this->sumOfType(TransactionType::Expense, 'expenses'))
            ->selectRaw(...$this->sumOfType(TransactionType::Income, 'income'))
            ->whereDoesntHave('tags')
            ->groupBy($transaction->qualifyColumn($currencyKey))
            ->get();

        return $rows->mapWithKeys(fn (Transaction $row): array => [
            $this->text($row->getAttribute($currencyKey)) => $this->tagRow(
                null,
                null,
                $this->decimal($row->getAttribute('income')),
                $this->decimal($row->getAttribute('expenses')),
            ),
        ])->all();
    }

    /**
     * One row of the tag breakdown, where a null tag is the
     * untagged money of the currency. A row with no budget
     * behind it carries a null block.
     *
     * @param  numeric-string  $income
     * @param  numeric-string  $expenses
     * @return array<string, mixed>
     */
    protected function tagRow(?string $id, ?string $name, string $income, string $expenses, ?BudgetPeriod $budget = null): array
    {
        return [
            'budget' => $budget instanceof BudgetPeriod
                ? $this->budgetProgress($budget, $income, $expenses)
                : null,
            'expenses' => $expenses,
            'id' => $id,
            'income' => $income,
            'name' => $name,
            'net' => bcsub($income, $expenses, self::SCALE),
        ];
    }

    /**
     * Order tag rows by what they spent, biggest first, and
     * settle a tie on the tag name.
     *
     * @param  array<string, mixed>  $first
     * @param  array<string, mixed>  $second
     */
    protected function byExpensesThenName(array $first, array $second): int
    {
        $spending = bccomp($this->decimal($second['expenses']), $this->decimal($first['expenses']), self::SCALE);

        return $spending === 0
            ? strcmp($this->text($first['name']), $this->text($second['name']))
            : $spending;
    }

    /**
     * A total of the month next to how it moved since the month
     * before. The delta is null when there is no month to
     * compare against, and its percentage is null when the
     * month before had nothing.
     *
     * @param  numeric-string  $amount
     * @param  numeric-string|null  $previous
     * @param  numeric-string  $amount
     * @param  numeric-string|null  $previous
     * @return array<string, mixed>
     */
    protected function total(string $amount, ?string $previous): array
    {
        if ($previous === null) {
            return ['amount' => $amount, 'delta' => null];
        }

        $difference = bcsub($amount, $previous, self::SCALE);

        // A percentage of nothing says nothing, so the page is
        // given a dash instead of a meaningless number.
        $percentage = bccomp($previous, '0', self::SCALE) === 0
            ? null
            : (float) bcdiv(bcmul($difference, '100', self::SCALE), $previous, 4);

        return [
            'amount' => $amount,
            'delta' => [
                'amount' => $difference,
                'percentage' => $percentage,
            ],
        ];
    }

    /**
     * What the team moved between its own accounts in the
     * month, or null when it moved nothing.
     *
     * @param  array{expenses: numeric-string, income: numeric-string, moved_in: numeric-string, moved_out: numeric-string}  $totals
     * @return array{moved_in: numeric-string, moved_out: numeric-string}|null
     */
    protected function transfers(array $totals): ?array
    {
        $isQuiet = bccomp($totals['moved_in'], '0', self::SCALE) === 0
            && bccomp($totals['moved_out'], '0', self::SCALE) === 0;

        return $isQuiet ? null : [
            'moved_in' => $totals['moved_in'],
            'moved_out' => $totals['moved_out'],
        ];
    }

    /**
     * The totals of a currency the team did not touch
     * in the month.
     *
     * @return array{expenses: numeric-string, income: numeric-string, moved_in: numeric-string, moved_out: numeric-string}
     */
    protected function emptyTotals(): array
    {
        return [
            'expenses' => '0',
            'income' => '0',
            'moved_in' => '0',
            'moved_out' => '0',
        ];
    }
}
