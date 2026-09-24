<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Reports;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Reports\Team\PeriodSummary;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Totals the team's income and expenses over a range of dates,
 * grouped by category, account or tag and kept apart per
 * currency, without listing a single transaction.
 */
#[Name('summarize')]
#[Description("Total the team's income and expenses between two dates (both included), grouped by `category`, `account` or `tag`, with one block per currency (currencies are never mixed or converted). Only active accounts count; transfers and opening balances are left out. With `group_by: tag`, a transaction counts in full under every tag it carries and the row with null id and name is untagged money, so the tag rows may add up to more than `total`, which counts every transaction once. Narrow with `type` (income or expense), `account` (account id) or `currency` (ISO code). A `from` before the plan's history window is moved up to its start; the result says which dates were summed.")]
#[IsReadOnly]
class Summarize extends Tool
{
    use InteractsWithTeams;
    use PresentsRecords;

    /**
     * The groupings a summary may be broken down by.
     *
     * @var array<int, string>
     */
    protected const array GROUPS = ['category', 'account', 'tag'];

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->teamSchema($schema),
            'from' => $schema->string()
                ->description('The first day to include, as "YYYY-MM-DD".')
                ->required(),
            'to' => $schema->string()
                ->description('The last day to include, as "YYYY-MM-DD".')
                ->required(),
            'group_by' => $schema->string()
                ->enum(self::GROUPS)
                ->description('What to group the totals by.')
                ->required(),
            'type' => $schema->string()
                ->enum([TransactionType::Income->value, TransactionType::Expense->value])
                ->description('Only sum income or only expenses. Leave out for both.'),
            'account' => $schema->string()
                ->description('Only sum this account (its id, as listed by `list_accounts`).'),
            'currency' => $schema->string()
                ->description('Only sum this currency, as an ISO code such as "USD".'),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, PeriodSummary $summary): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team) use ($request, $summary): ResponseFactory {
            $request->validate([
                'from' => ['required', 'date_format:Y-m-d'],
                'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
                'group_by' => ['required', Rule::in(self::GROUPS)],
                'type' => ['sometimes', 'nullable', Rule::in([TransactionType::Income->value, TransactionType::Expense->value])],
                'account' => ['sometimes', 'nullable', 'string'],
                'currency' => ['sometimes', 'nullable', 'string'],
            ]);

            [$from, $to] = $this->range($team, $request->string('from')->value(), $request->string('to')->value());

            // Only an active account of the team can be summed.
            $account = $request->filled('account')
                ? $this->findOrFail($team->accounts()->isActive(), $request->get('account'), 'account')
                : null;

            $currency = $request->filled('currency')
                ? $this->currency($request->string('currency')->value())
                : null;

            $movements = $summary->movements(
                $team,
                $from,
                $to,
                $request->filled('type') ? TransactionType::from($request->string('type')->value()) : null,
                $account instanceof Account ? (string) $account->getKey() : null,
                $currency instanceof Currency ? (string) $currency->getKey() : null,
            );

            $groupBy = $request->string('group_by')->value();

            // Every grouping sums the same movements.
            $rows = match ($groupBy) {
                'account' => $summary->byAccount($team, clone $movements),
                'tag' => $summary->byTag(clone $movements),
                default => $summary->byCategory(clone $movements),
            };

            // A movement sits in exactly one category, so the
            // category rows give the total even when the tag
            // rows overlap.
            $totalRows = $groupBy === 'tag' ? $summary->byCategory(clone $movements) : $rows;

            return Response::structured([
                'from' => $from,
                'to' => $to,
                'group_by' => $groupBy,
                'currencies' => $this->perCurrency($rows, $totalRows, $groupBy),
            ]);
        });
    }

    /**
     * The dates to sum, with a start before the plan's history
     * window moved up to it. A range that ends before the
     * window has nothing the team may see.
     *
     * @return array{0: string, 1: string}
     */
    protected function range(Team $team, string $from, string $to): array
    {
        $windowStart = Transaction::historyWindowStart(Feature::ViewHistory->limitOn($team->plan()));

        if (! $windowStart instanceof \Carbon\CarbonImmutable || CarbonImmutable::parse($from)->greaterThanOrEqualTo($windowStart)) {
            return [$from, $to];
        }

        if (CarbonImmutable::parse($to)->lessThan($windowStart)) {
            throw new ToolError("This team's plan only shows history from {$windowStart->toDateString()}. Upgrade to Pro to see older months: ".$this->upgradeUrl($team, Feature::ViewHistory));
        }

        return [$windowStart->toDateString(), $to];
    }

    /**
     * Find a currency by its ISO code, ignoring case.
     */
    protected function currency(string $code): Currency
    {
        $currency = Currency::query()
            ->where('code', Str::upper(Str::trim($code)))
            ->first();

        if (! $currency instanceof Currency) {
            throw new ToolError("Unknown currency \"{$code}\".");
        }

        return $currency;
    }

    /**
     * Group the summed rows into one block per currency, each
     * with its rows, biggest spender first, and its total, read
     * from rows that count every movement once.
     *
     * @param  array<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}>  $rows
     * @param  array<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}>  $totalRows
     * @return array<int, array<string, mixed>>
     */
    protected function perCurrency(array $rows, array $totalRows, string $groupBy): array
    {
        $grouped = new Collection($rows)->groupBy('currency_id');
        $totals = new Collection($totalRows)->groupBy('currency_id');

        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Modules\Core\Catalog\Models\Currency> $currencies */
        $currencies = Currency::query()
            ->whereKey($grouped->keys()->all())
            ->orderBy('code')
            ->get();

        return $currencies
            ->map(function (Currency $currency) use ($grouped, $totals, $groupBy): array {
                /** @var \Illuminate\Support\Collection<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}> $group */
                $group = $grouped->get((string) $currency->getKey(), new Collection);

                $decimals = $currency->decimal_places;

                // The untagged row reads last, after every tag.
                $sorted = $group->sort(fn (array $first, array $second): int => [$first['id'] === null, bccomp($second['expenses'], $first['expenses'], 18), (string) $first['name']]
                    <=> [$second['id'] === null, 0, (string) $second['name']]);

                /** @var \Illuminate\Support\Collection<int, array{currency_id: string, id: string|null, name: string|null, income: numeric-string, expenses: numeric-string}> $counted */
                $counted = $totals->get((string) $currency->getKey(), new Collection);

                $income = $counted->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['income'], 18), '0');
                $expenses = $counted->reduce(fn (string $sum, array $row): string => bcadd($sum, $row['expenses'], 18), '0');

                return [
                    'currency' => $currency->code,
                    'groups' => $sorted
                        ->map(fn (array $row): array => [
                            $groupBy => $groupBy === 'category'
                                ? ['value' => $row['id'], 'label' => $row['name']]
                                : ['id' => $row['id'], 'name' => $row['name']],
                            'income' => $this->amount($row['income'], $decimals),
                            'expenses' => $this->amount($row['expenses'], $decimals),
                            'net' => $this->amount(bcsub($row['income'], $row['expenses'], 18), $decimals),
                        ])
                        ->values()
                        ->all(),
                    'total' => [
                        'income' => $this->amount($income, $decimals),
                        'expenses' => $this->amount($expenses, $decimals),
                        'net' => $this->amount(bcsub($income, $expenses, 18), $decimals),
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
