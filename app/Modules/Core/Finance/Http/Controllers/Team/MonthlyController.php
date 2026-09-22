<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Controllers\Team;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetTargetKind;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Controllers\Team\Concerns\CalculatesBudgetProgress;
use App\Modules\Core\Finance\Http\Controllers\Team\Concerns\SumsTaggedActivity;
use App\Modules\Core\Finance\Http\Requests\Team\Monthly\IndexRequest;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class MonthlyController
{
    use CalculatesBudgetProgress;
    use SumsTaggedActivity;

    /**
     * The format the selected month travels in, both in
     * the URL and in the page props.
     */
    protected const string MONTH_FORMAT = 'Y-m';

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
     * Display the monthly report for the team.
     */
    public function index(IndexRequest $request, Team $team): Response|RedirectResponse
    {
        $latest = CarbonImmutable::now()->startOfMonth();
        $selected = $request->selectedMonth();

        // A month that has not happened yet has nothing to
        // report, so the URL is cleaned back to the current
        // month rather than shown empty.
        if ($selected instanceof CarbonImmutable && $selected->greaterThan($latest)) {
            return to_route('finance.teams.monthly.index', ['team' => $team]);
        }

        $month = $selected ?? $latest;
        $earliest = $this->earliestMonth($team, $latest);

        // A month before the plan's window stays reachable but
        // carries no figures, so downgrading hides history
        // without destroying it.
        $isLocked = $month->lessThan($earliest);

        return Inertia::render('finance/teams/monthly', [
            // A plan without budgets still learns they exist, so
            // the tag table carries a single upsell instead of
            // budget blocks.
            'can_budget' => Feature::ManageBudgets->enabled($team),
            'earliest_month' => $earliest->format(self::MONTH_FORMAT),
            'figures' => $isLocked ? [] : Inertia::defer(
                fn (): array => $this->figures($team, $month, $earliest),
                'figures',
            ),
            'has_accounts' => $team->accounts()
                ->isActive()
                ->exists(),
            'is_locked' => $isLocked,
            'latest_month' => $latest->format(self::MONTH_FORMAT),
            'month' => $month->format(self::MONTH_FORMAT),
            'team' => $team,
        ]);
    }

    /**
     * The oldest month the team may open: the month of its
     * first transaction, raised to the window the plan
     * allows.
     */
    protected function earliestMonth(Team $team, CarbonImmutable $latest): CarbonImmutable
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
    protected function figures(Team $team, CarbonImmutable $month, CarbonImmutable $earliest): array
    {
        $currencies = $this->currencies($team, $earliest);
        $tags = $this->tagsByCurrency($team, $month);

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
            ->map(function (Currency $currency) use ($totals, $previousTotals, $isComparable, $tags, $summed): array {
                $key = (string) $currency->getKey();
                $current = $totals->get($key, $this->emptyTotals());
                $previous = $isComparable
                    ? $previousTotals->get($key, $this->emptyTotals())
                    : null;

                return [
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
