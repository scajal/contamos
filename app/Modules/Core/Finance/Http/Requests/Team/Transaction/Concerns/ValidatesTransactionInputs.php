<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Instant;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

/**
 * The validation rules a transaction recorded by hand must
 * satisfy, built from plain input arrays so the web Store and
 * Update requests and the MCP tools hold every entry to the
 * very same rules.
 */
trait ValidatesTransactionInputs
{
    /**
     * Get the rules of a transaction's own fields, for the input
     * as it will be validated: amounts normalised, occurred_at in
     * UTC and the quote filled in. When a stored transaction is
     * given, its current type and its attached tags stay allowed.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, array<mixed>>
     */
    protected function transactionRules(Team $team, array $input, ?Transaction $transaction = null): array
    {
        $accountKey = new Transaction()->account()->getForeignKeyName();
        $currencyKey = new Transaction()->currency()->getForeignKeyName();

        $account = $this->submittedAccount($team, $input[$accountKey] ?? null);
        $currency = $this->submittedCurrency($input[$currencyKey] ?? null);

        // Ids of tags already attached to the transaction, which
        // stay valid even if they have since been deactivated.
        $attachedTagIds = $transaction?->tags->pluck(new Tag()->getKeyName())->all() ?? [];

        return [
            $accountKey => [
                'required',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'amount' => [
                'required',
                $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gt:0',
            ],
            'category' => $this->transactionCategoryRules($this->submittedTransactionType($input['type'] ?? null)),
            $currencyKey => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in($account?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'quote' => [
                'required',
                'decimal:0,18',
            ],
            'tag_ids' => [
                'sometimes',
                'array',
            ],
            'tag_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists(Tag::class, new Tag()->getKeyName())
                    ->where(new Tag()->team()->getForeignKeyName(), $team->getKey())
                    ->where(fn (Builder $query): Builder => $query->where('is_active', true)->when(
                        $attachedTagIds !== [],
                        fn (Builder $query): Builder => $query->orWhereIn(new Tag()->getKeyName(), $attachedTagIds),
                    )),
            ],
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::in([
                    ...array_column(TransactionType::recordableCases(), 'value'),
                    ...($transaction instanceof Transaction ? [$transaction->type->value] : []),
                ]),
            ],
        ];
    }

    /**
     * Get the rules of the schedule a new transaction repeats
     * on. They only bite when the input asks it to repeat, and
     * then a valid timezone is required too, since the rule's
     * schedule is computed in it.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, array<mixed>>
     */
    protected function repeatRules(array $input): array
    {
        $repeats = $this->repeats($input);

        return [
            'repeat' => [
                'sometimes',
                'boolean',
            ],
            'recurring_ends_on' => [
                'nullable',
                'date',
                'after_or_equal:recurring_starts_on',
            ],
            'recurring_frequency' => [
                Rule::requiredIf($repeats),
                Rule::in(array_column(Frequency::cases(), 'value')),
            ],
            'recurring_interval' => [
                Rule::requiredIf($repeats),
                'integer',
                'between:1,99',
            ],
            'recurring_starts_on' => [
                Rule::requiredIf($repeats),
                'date',
            ],
            'recurring_time_of_day' => [
                Rule::requiredIf($repeats),
                'date_format:H:i:s',
            ],
            'timezone' => [
                function (string $attribute, mixed $value, Closure $fail) use ($repeats): void {
                    // Only a repeating transaction needs a valid
                    // timezone. A one-off entry tolerates a missing
                    // or invalid one, keeping occurred_at as sent.
                    if ($repeats && ! Instant::isZone($value)) {
                        $fail(trans('The timezone is invalid.'));
                    }
                },
            ],
        ];
    }

    /**
     * Get the start date and time of day a repeating
     * transaction's schedule takes from the local wall-clock
     * value it occurred at, read before it becomes UTC. A date
     * alone starts at noon, where such a date is recorded.
     *
     * @return array{recurring_starts_on?: string, recurring_time_of_day?: string}
     */
    protected function repeatStartFrom(mixed $localOccurredAt): array
    {
        if (! is_string($localOccurredAt) || preg_match('/^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/', $localOccurredAt, $matches) !== 1) {
            return [];
        }

        return [
            'recurring_starts_on' => $matches[1],
            'recurring_time_of_day' => isset($matches[2]) ? "{$matches[2]}:00" : '12:00:00',
        ];
    }

    /**
     * Whether the input asks the transaction to repeat.
     *
     * @param  array<array-key, mixed>  $input
     */
    protected function repeats(array $input): bool
    {
        return filter_var($input['repeat'] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Get the rules for the category, which every movement
     * recorded by hand carries, restricted to the list its type
     * offers.
     *
     * @return array<int, \Illuminate\Validation\Rules\Enum|string>
     */
    protected function transactionCategoryRules(?TransactionType $type): array
    {
        $cases = $type instanceof TransactionType ? Category::casesFor($type) : [];

        // A type that is never recorded by hand, such as an
        // opening balance, carries no category at all.
        if ($cases === []) {
            return ['prohibited'];
        }

        return [
            'required',
            'string',
            Rule::enum(Category::class)->only($cases),
        ];
    }

    /**
     * Get the team's account the input names, if any.
     */
    protected function submittedAccount(Team $team, mixed $id): ?Account
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = $team->accounts()->find($id);

        return $account;
    }

    /**
     * Get the currency the input names, if any.
     */
    protected function submittedCurrency(mixed $id): ?Currency
    {
        if (! is_string($id) || $id === '') {
            return null;
        }

        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()->find($id);

        return $currency;
    }

    /**
     * Get the type the input records the movement with.
     */
    protected function submittedTransactionType(mixed $type): ?TransactionType
    {
        return is_string($type) ? TransactionType::tryFrom($type) : null;
    }
}
