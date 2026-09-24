<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ValidatesTransactionInputs;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns the arguments of the MCP transaction tools into the
 * attributes the transaction Actions take, then validates them
 * with the very rules the web Store and Update requests apply.
 * Arguments are plain arrays, so a single call and each row of
 * a bulk import resolve the same way.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 * @mixin \App\Modules\Mcp\Tools\Concerns\InteractsWithTeams
 * @mixin \App\Modules\Mcp\Tools\Concerns\NormalizesInputs
 * @mixin \App\Modules\Mcp\Tools\Concerns\PresentsRecords
 */
trait ResolvesTransactionArguments
{
    use ValidatesTransactionInputs;

    /**
     * Get the schema of a transaction's own fields.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function transactionArgumentsSchema(JsonSchema $schema, bool $required = true): array
    {
        $type = $schema->string()
            ->enum(array_column(TransactionType::recordableCases(), 'value'))
            ->description('Whether money came in (income) or went out (expense).');

        $account = $schema->string()
            ->description('The id of the account, as listed by `list_accounts`.');

        $amount = $schema->string()
            ->description('The amount as a positive decimal string with a dot, such as "1250.50", never signed.');

        $category = $schema->string()
            ->enum(array_column(Category::cases(), 'value'))
            ->description('The category. Income takes one of: '.$this->categoriesFor(TransactionType::Income).'. Expense takes one of: '.$this->categoriesFor(TransactionType::Expense).'.');

        $occurredAt = $this->occurredAtSchema($schema)['occurred_at'];

        return [
            'type' => $required ? $type->required() : $type,
            'account' => $required ? $account->required() : $account,
            'amount' => $required ? $amount->required() : $amount,
            'category' => $required ? $category->required() : $category,
            'occurred_at' => $required ? $occurredAt->required() : $occurredAt,
            ...$this->timezoneSchema($schema),
            'currency' => $schema->string()
                ->description('The ISO code of the currency, such as "USD". Needed only when the account holds several.'),
            'quote' => $schema->string()
                ->description('The exchange rate to record, as a decimal string. Leave out to use the rate recorded for the date.'),
            'description' => $schema->string()
                ->description('A short note on what it was.'),
            'tags' => $schema->array()
                ->items($schema->string())
                ->description('Tags by id or exact name, as listed by `list_tags`. Tags are never created.'),
        ];
    }

    /**
     * Get the schema of the schedule a new transaction repeats
     * on.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function repeatArgumentsSchema(JsonSchema $schema): array
    {
        return [
            'repeat' => $schema->boolean()
                ->description('Make the transaction repeat on a schedule (Pro plan). Requires `timezone` and `recurring_frequency`.'),
            'recurring_frequency' => $schema->string()
                ->enum(array_column(Frequency::cases(), 'value'))
                ->description('How often it repeats.'),
            'recurring_interval' => $schema->integer()
                ->description('Repeat every this many periods, from 1 to 99. Defaults to 1.'),
            'recurring_starts_on' => $schema->string()
                ->description('The first due date, "YYYY-MM-DD". Defaults to the date of `occurred_at`. The first occurrence is recorded now only when it is due by today.'),
            'recurring_ends_on' => $schema->string()
                ->description('The last date it may repeat on, "YYYY-MM-DD". Leave out to repeat indefinitely.'),
            'recurring_time_of_day' => $schema->string()
                ->description('The local time each occurrence is recorded at, "HH:MM". Defaults to the time of `occurred_at`, or noon.'),
        ];
    }

    /**
     * Resolve the arguments of one transaction into validated
     * attributes for \App\Modules\Core\Finance\Actions\Team\CreateTransactionEntry
     * (with the `repeat` schedule) or, given the transaction
     * being edited, for \App\Modules\Core\Finance\Actions\Team\UpdateTransaction,
     * where each argument left out keeps its current value.
     * Returns the attributes and the ids of the tags.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \App\Modules\Mcp\Exceptions\ToolError
     */
    protected function transactionAttributes(Team $team, array $arguments, ?Transaction $transaction = null): array
    {
        $given = fn (string $key): bool => ! $transaction instanceof Transaction || array_key_exists($key, $arguments);

        $transaction?->loadMissing('account', 'currency', 'tags');

        // Resolve the account among the team's own.
        $account = $given('account') || ! $transaction instanceof Transaction
            ? $this->transactionAccount($team, $arguments['account'] ?? null)
            : $transaction->account;

        // An inactive account takes no new movements, as the web
        // form never offers one; a transaction already on it
        // may stay there.
        if (! $account->is_active && $account->getKey() !== $transaction?->account_id) {
            throw new ToolError("The account \"{$account->name}\" is inactive. Reactivate it with `set_account_active` first, or pick another account.");
        }

        // Resolve the currency among the account's, keeping the
        // current one while the account is unchanged.
        $currency = ! $given('currency') && $transaction instanceof Transaction && $transaction->account_id === $account->getKey()
            ? $transaction->currency
            : $this->accountCurrency($account, $arguments['currency'] ?? null);

        // Resolve when it happened, in UTC.
        $currentOccurredAt = $transaction?->occurred_at->utc()->toIso8601String();

        $occurredAt = $given('occurred_at') || $currentOccurredAt === null
            ? $this->requiredOccurredAt($arguments['occurred_at'] ?? null, $arguments['timezone'] ?? null)
            : $currentOccurredAt;

        // Keep the stored rate while the currency and date are
        // unchanged; otherwise the one given, or the one recorded
        // for the date.
        $quote = ! $this->hasTransactionValue($arguments['quote'] ?? null)
            && $transaction instanceof Transaction
            && $transaction->currency_id === $currency->getKey()
            && $currentOccurredAt === $occurredAt
                ? (string) $transaction->quote
                : $this->quote($currency, $occurredAt, $arguments['quote'] ?? null);

        // Resolve the tags by id or name, keeping the ones the
        // transaction already carries allowed.
        $attachedTagIds = $transaction?->tags->pluck(new Tag()->getKeyName())->filter(fn (mixed $value): bool => is_string($value))->values()->all() ?? [];

        $tagIds = $given('tags')
            ? $this->tagIds($team, $arguments['tags'] ?? null, $attachedTagIds)
            : $attachedTagIds;

        $attributes = [
            new Transaction()->account()->getForeignKeyName() => $account->getKey(),
            'amount' => $given('amount') || ! $transaction instanceof Transaction
                ? $this->transactionDecimal($arguments['amount'] ?? null)
                : $this->amount($transaction->amount, $transaction->decimal_places),
            'category' => $given('category') || ! $transaction instanceof Transaction
                ? ($arguments['category'] ?? null)
                : $transaction->category?->value,
            new Transaction()->currency()->getForeignKeyName() => $currency->getKey(),
            'occurred_at' => $occurredAt,
            'quote' => $quote,
            'tag_ids' => $tagIds,
            'type' => $given('type') || ! $transaction instanceof Transaction
                ? ($arguments['type'] ?? null)
                : $transaction->type->value,
        ];

        // A description left out of an update is left alone.
        if ($given('description') && array_key_exists('description', $arguments)) {
            $attributes['description'] = $arguments['description'];
        }

        // A new transaction may repeat on a schedule.
        if (! $transaction instanceof Transaction) {
            $attributes = [...$attributes, ...$this->repeatAttributes($team, $arguments)];
        }

        // Validate with the same rules as the web requests.
        $rules = $transaction instanceof Transaction
            ? $this->transactionRules($team, $attributes, $transaction)
            : [...$this->transactionRules($team, $attributes), ...$this->repeatRules($attributes)];

        $validated = Validator::make($attributes, $rules, [], [
            new Transaction()->account()->getForeignKeyName() => 'account',
            new Transaction()->currency()->getForeignKeyName() => 'currency',
            'tag_ids.*' => 'tag',
        ])->validate();

        return [$this->validatedOnly($validated, array_keys(Arr::except($attributes, ['tag_ids']))), $tagIds];
    }

    /**
     * Get the schedule attributes of a new transaction: none for
     * a one-off, and for a repeating one the schedule with its
     * start taken from the local time it occurred at, the way
     * the web form does. Repeating needs the team's plan to
     * include it.
     *
     * @param  array<array-key, mixed>  $arguments
     * @return array<string, mixed>
     */
    protected function repeatAttributes(Team $team, array $arguments): array
    {
        if (! $this->repeats($arguments)) {
            return [];
        }

        // Repeating is a Pro feature.
        $this->ensureFeature($team, Feature::RecurTransactions);

        $timeOfDay = $arguments['recurring_time_of_day'] ?? null;

        return [
            ...$this->repeatStartFrom($arguments['occurred_at'] ?? null),
            ...$this->validatedOnly($arguments, ['recurring_ends_on', 'recurring_frequency', 'recurring_starts_on']),
            'repeat' => true,
            'recurring_interval' => $arguments['recurring_interval'] ?? 1,
            ...(is_string($timeOfDay) && preg_match('/^\d{2}:\d{2}$/', $timeOfDay) === 1
                ? ['recurring_time_of_day' => "{$timeOfDay}:00"]
                : $this->validatedOnly($arguments, ['recurring_time_of_day'])),
            'timezone' => $arguments['timezone'] ?? null,
        ];
    }

    /**
     * Find a transaction of the team the plan still shows,
     * reporting one past the plan's history window as not found,
     * the way the web does.
     */
    protected function visibleTransaction(Team $team, mixed $id): Transaction
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Transaction $transaction */
        $transaction = $this->findOrFail($team->transactions(), $id, 'transaction');

        if (! $transaction->isWithinHistoryWindow($this->historyWindowStart($team))) {
            throw new ToolError('Transaction not found.');
        }

        return $transaction;
    }

    /**
     * Find a transaction of the team that may be changed or
     * deleted directly: one the plan still shows and that no
     * transfer owns.
     */
    protected function writableTransaction(Team $team, mixed $id): Transaction
    {
        $transaction = $this->visibleTransaction($team, $id);

        if ($transaction->belongsToTransfer()) {
            throw new ToolError('This transaction is part of a transfer; update the transfer instead (transfer id: '.$transaction->transfer_id.').');
        }

        return $transaction;
    }

    /**
     * Get the first day of history the team's plan still opens,
     * or null when it keeps everything.
     */
    protected function historyWindowStart(Team $team): ?CarbonImmutable
    {
        return Transaction::historyWindowStart(Feature::ViewHistory->limitOn($team->plan()));
    }

    /**
     * Find one of the team's accounts by its id.
     */
    protected function transactionAccount(Team $team, mixed $id): Account
    {
        if ($id === null || $id === '') {
            throw ValidationException::withMessages(['account' => "The account is required. Call `list_accounts` to see the team's accounts."]);
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Account $account */
        $account = $this->findOrFail($team->accounts(), $id, 'account');

        return $account;
    }

    /**
     * Resolve a required date or date and time into UTC.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function requiredOccurredAt(mixed $value, mixed $timezone): string
    {
        if ($value === null || $value === '') {
            throw ValidationException::withMessages(['occurred_at' => 'The date it happened is required.']);
        }

        return $this->occurredAt($value, $timezone);
    }

    /**
     * Whether a figure was actually given.
     */
    protected function hasTransactionValue(mixed $value): bool
    {
        return (is_string($value) && Str::trim($value) !== '') || is_int($value) || is_float($value);
    }

    /**
     * Turn a figure the assistant sent as a number into a
     * decimal string; anything else is left for the rules.
     */
    protected function transactionDecimal(mixed $value): mixed
    {
        return match (true) {
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => Str::trim($value),
            default => $value,
        };
    }

    /**
     * List the category values a type offers.
     */
    protected function categoriesFor(TransactionType $type): string
    {
        return collect(Category::casesFor($type))
            ->map(fn (Category $category): string => $category->value)
            ->implode(', ');
    }
}
