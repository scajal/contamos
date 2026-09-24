<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Transactions;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Actions\Team\CreateTransactionEntry;
use App\Modules\Core\Finance\Actions\Team\EvaluateBudgetAlerts;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use App\Modules\Mcp\Exceptions\ToolError;
use App\Modules\Mcp\Tools\Concerns\InteractsWithTeams;
use App\Modules\Mcp\Tools\Concerns\NormalizesInputs;
use App\Modules\Mcp\Tools\Concerns\PresentsRecords;
use App\Modules\Mcp\Tools\Concerns\ResolvesTransactionArguments;
use App\Modules\Support\Helpers\TeamActivity;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

/**
 * Imports the income and expense lines of a bank statement in
 * one go: every row is validated like `create_transaction`, the
 * batch is written all or nothing, suspected duplicates stop it
 * unless confirmed, and the whole import can be undone at once.
 */
#[Name('import_transactions')]
#[Description('Import up to 200 income and expense rows (for example the lines of a bank statement) in one call. Set `account`, `currency` and `timezone` once for the batch; any row may override them. Every row is validated like `create_transaction`; if any row fails, nothing is written and the errors are keyed by row index. A row matching an existing transaction (same account, currency, type and amount on the same local day), or another row of the batch, is a suspected duplicate and blocks the import unless `allow_duplicates` is true. Always call it first with `dry_run: true`, show the user the summary and any suspected duplicates, and import only after they confirm. Returns an `import_id` that `delete_import` can undo.')]
class ImportTransactions extends Tool
{
    use InteractsWithTeams;
    use NormalizesInputs;
    use PresentsRecords;
    use ResolvesTransactionArguments;

    /**
     * The most rows one call may import.
     */
    public const int MAX_ROWS = 200;

    /**
     * The row arguments a batch default applies to.
     */
    protected const array DEFAULTS = ['account', 'currency', 'timezone'];

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        $row = $this->transactionArgumentsSchema($schema, required: false);

        return [
            ...$this->teamSchema($schema),
            'account' => $schema->string()
                ->description('The id of the account every row goes to, unless a row names its own.'),
            'currency' => $schema->string()
                ->description('The ISO code of the currency of every row, unless a row names its own. Needed only when the account holds several.'),
            ...$this->timezoneSchema($schema),
            'rows' => $schema->array()
                ->items($schema->object($row))
                ->description('Up to '.self::MAX_ROWS.' rows, each with `type` (income or expense), `amount`, `category`, `occurred_at` and optionally `description`, `tags`, `quote`, `account`, `currency` and `timezone`.')
                ->required(),
            'dry_run' => $schema->boolean()
                ->description('Validate and check for duplicates without writing anything, returning what would be imported.'),
            'allow_duplicates' => $schema->boolean()
                ->description('Import rows even when they look like duplicates. Only after the user confirmed they are genuine.'),
        ];
    }

    /**
     * Handle the tool call.
     */
    public function handle(Request $request, CreateTransactionEntry $createTransactionEntry, EvaluateBudgetAlerts $evaluateBudgetAlerts): Response|ResponseFactory
    {
        return $this->forTeam($request, function (Team $team, User $user) use ($request, $createTransactionEntry, $evaluateBudgetAlerts): Response|ResponseFactory {
            $this->validateBatch($request);

            /** @var array<int, array<string, mixed>> $rows */
            $rows = is_array($rows = $request->get('rows')) ? array_values($rows) : [];

            // Resolve and validate every row before anything is
            // written, collecting the errors of all of them.
            $resolved = $this->resolveRows($team, $request, $rows);

            // Look for rows that are already recorded, or that
            // repeat an earlier row of the batch.
            $duplicates = $this->duplicates($team, $resolved);

            $summary = [
                'rows' => count($resolved),
                'totals' => $this->totals($resolved),
                'duplicates' => $duplicates,
            ];

            // A dry run stops here, having written nothing.
            if ($request->boolean('dry_run')) {
                return Response::structured(['dry_run' => true, ...$summary]);
            }

            if ($duplicates !== [] && ! $request->boolean('allow_duplicates')) {
                return Response::error('Nothing was imported: '.count($duplicates).' row(s) look like duplicates: '.json_encode($duplicates, JSON_UNESCAPED_SLASHES).'. Show them to the user; if they are genuine, call again with `allow_duplicates: true`, otherwise remove those rows.');
            }

            // Write every row inside one database transaction and
            // one activity batch, whose id names the import.
            $importId = TeamActivity::within(fn (): ?string => DB::transaction(function () use ($team, $user, $resolved, $createTransactionEntry): ?string {
                foreach ($resolved as $row) {
                    $createTransactionEntry->for($team)->by($user)->using($row['attributes'], $row['tag_ids']);
                }

                return TeamActivity::uuid();
            }));

            // Alert once about the budgets the whole import pushed
            // past a threshold, rather than once per row.
            $evaluateBudgetAlerts->for($team)
                ->using(array_map(fn (array $row): CarbonImmutable => CarbonImmutable::parse($this->text($row['attributes']['occurred_at'])), $resolved));

            return Response::structured([
                'import_id' => $importId,
                'created' => $summary['rows'],
                'totals' => $summary['totals'],
                'duplicates_allowed' => $duplicates,
            ]);
        });
    }

    /**
     * Validate the shape of the batch itself.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateBatch(Request $request): void
    {
        $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:'.self::MAX_ROWS],
            'rows.*' => ['array'],
            'dry_run' => ['sometimes', 'boolean'],
            'allow_duplicates' => ['sometimes', 'boolean'],
        ], [
            'rows.max' => 'At most '.self::MAX_ROWS.' rows can be imported per call. Split the statement into several calls.',
        ]);
    }

    /**
     * Resolve every row with the batch defaults applied, the way
     * `create_transaction` resolves one, refusing the whole batch
     * with the errors keyed by row index when any row fails.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{attributes: array<string, mixed>, tag_ids: array<int, string>, timezone: string|null}>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function resolveRows(Team $team, Request $request, array $rows): array
    {
        $defaults = array_filter(
            Arr::only($request->all(), self::DEFAULTS),
            fn (mixed $value): bool => $value !== null && $value !== '',
        );

        $resolved = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            // An import never schedules repeats.
            $arguments = [...$defaults, ...Arr::except($row, ['repeat']), 'repeat' => false];

            try {
                [$attributes, $tagIds] = $this->transactionAttributes($team, $arguments);

                $resolved[$index] = [
                    'attributes' => $attributes,
                    'tag_ids' => $tagIds,
                    'timezone' => $this->timezone($arguments['timezone'] ?? null),
                ];
            } catch (ValidationException $exception) {
                // The client only sees the messages, not their keys,
                // so each message names the row and field it is about.
                foreach ($exception->errors() as $field => $messages) {
                    $errors["rows.{$index}.{$field}"] = array_map(fn (mixed $message): string => "rows.{$index}.{$field}: {$this->text($message)}", Arr::wrap($messages));
                }
            } catch (ToolError $error) {
                $errors["rows.{$index}"] = ["rows.{$index}: {$error->getMessage()}"];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }

    /**
     * Find the suspected duplicates: rows matching a recorded
     * transaction, or an earlier row of the batch, on account,
     * currency, type and amount on the same local calendar day.
     *
     * @param  array<int, array{attributes: array<string, mixed>, tag_ids: array<int, string>, timezone: string|null}>  $rows
     * @return array<int, array{row: int, existing_transaction_id: string}|array{row: int, duplicate_of_row: int}>
     */
    protected function duplicates(Team $team, array $rows): array
    {
        $recorded = [];
        $repeated = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $attributes = $row['attributes'];

            // The row's local day, as a UTC range.
            $day = CarbonImmutable::parse($this->text($attributes['occurred_at']))->setTimezone($row['timezone'] ?? 'UTC');
            $from = $day->startOfDay()->utc();
            $until = $day->endOfDay()->utc();

            $existing = $team->transactions()
                ->where(new Transaction()->account()->getForeignKeyName(), $attributes['account_id'])
                ->where(new Transaction()->currency()->getForeignKeyName(), $attributes['currency_id'])
                ->where('type', $attributes['type'])
                ->where('amount', $attributes['amount'])
                ->whereBetween('occurred_at', [$from, $until])
                ->value(new Transaction()->getKeyName());

            if (is_string($existing)) {
                $recorded[] = ['row' => $index, 'existing_transaction_id' => $existing];

                continue;
            }

            // A row repeating an earlier one of the same batch.
            $key = implode('|', [
                $this->text($attributes['account_id']),
                $this->text($attributes['currency_id']),
                $this->text($attributes['type']),
                $this->normalizedAmount($attributes['amount']),
                $day->toDateString(),
            ]);

            if (array_key_exists($key, $seen)) {
                $repeated[] = ['row' => $index, 'duplicate_of_row' => $seen[$key]];

                continue;
            }

            $seen[$key] = $index;
        }

        // Both kinds, in the order of the rows.
        $duplicates = array_merge($recorded, $repeated);

        usort($duplicates, fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $duplicates;
    }

    /**
     * Total the rows per currency, income and expense apart, as
     * exact decimal strings.
     *
     * @param  array<int, array{attributes: array<string, mixed>, tag_ids: array<int, string>, timezone: string|null}>  $rows
     * @return array<int, array{currency: string, income: string, expense: string, rows: int}>
     */
    protected function totals(array $rows): array
    {
        $currencies = Currency::query()
            ->whereKey(collect($rows)->pluck('attributes.currency_id')->unique()->all())
            ->get()
            ->keyBy(new Currency()->getKeyName());

        $totals = [];

        foreach ($rows as $row) {
            /** @var \App\Modules\Core\Catalog\Models\Currency $currency */
            $currency = $currencies->get($this->text($row['attributes']['currency_id']));

            $totals[$currency->code] ??= ['currency' => $currency->code, 'income' => '0', 'expense' => '0', 'rows' => 0, 'decimals' => $currency->decimal_places];

            $side = $row['attributes']['type'] === 'income' ? 'income' : 'expense';

            $totals[$currency->code][$side] = bcadd($totals[$currency->code][$side], $this->normalizedAmount($row['attributes']['amount']), 18);
            $totals[$currency->code]['rows']++;
        }

        ksort($totals);

        return array_values(array_map(fn (array $total): array => [
            'currency' => $total['currency'],
            'income' => $this->amount($total['income'], $total['decimals']),
            'expense' => $this->amount($total['expense'], $total['decimals']),
            'rows' => $total['rows'],
        ], $totals));
    }

    /**
     * Get an amount as a plain numeric string bcmath accepts.
     *
     * @return numeric-string
     */
    protected function normalizedAmount(mixed $amount): string
    {
        $amount = $this->text($amount);

        return is_numeric($amount) ? bcadd($amount, '0', 18) : '0';
    }

    /**
     * Get a scalar attribute as a string.
     */
    protected function text(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
