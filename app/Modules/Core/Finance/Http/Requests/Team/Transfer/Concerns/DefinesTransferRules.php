<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * The validation rules of a transfer, shared by the transfer
 * Store and Update requests and the MCP transfer tools. Every
 * method takes plain parameters instead of reading a request.
 */
trait DefinesTransferRules
{
    /**
     * Get the rules a transfer's attributes must pass. When the
     * transfer being edited is given, the accounts, currencies,
     * and tags it already points at stay valid even if they have
     * since been deactivated.
     *
     * @param  array<array-key, mixed>  $input
     * @return array<string, array<mixed>>
     */
    protected function transferRules(Team $team, array $input, ?Transfer $transfer = null): array
    {
        $transfer?->loadMissing('tags', 'transactions');

        // Keys the transfer already points at, which stay valid even
        // if they have since been deactivated.
        $attachedTagIds = $transfer?->tags->pluck(new Tag()->getKeyName())->all() ?? [];
        $currentAccountIds = $transfer?->transactions->pluck(new Transaction()->account()->getForeignKeyName())->unique()->values()->all() ?? [];
        $currentCurrencyIds = $transfer?->transactions->pluck(new Transaction()->currency()->getForeignKeyName())->unique()->values()->all() ?? [];

        return [
            'amount_received' => [
                'required',
                $this->transferDecimalRule($input['destination_currency_id'] ?? null),
                'gt:0',
            ],
            'amount_sent' => [
                'required',
                $this->transferDecimalRule($input['origin_currency_id'] ?? null),
                'gt:0',
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],
            'destination_account_id' => [
                'required',
                'string',
                'different:origin_account_id',
                $this->transferAccountExists($team, $currentAccountIds),
            ],
            'destination_currency_id' => [
                'required',
                'string',
                $this->transferCurrencyExists($currentCurrencyIds),
                Rule::in($this->transferAccount($team, $input['destination_account_id'] ?? null)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'destination_fee' => [
                'sometimes',
                'nullable',
                $this->transferDecimalRule($input['destination_currency_id'] ?? null),
                'gt:0',
            ],
            'destination_quote' => [
                'required',
                'decimal:0,18',
            ],
            'occurred_at' => [
                'required',
                'date',
            ],
            'origin_account_id' => [
                'required',
                'string',
                $this->transferAccountExists($team, $currentAccountIds),
            ],
            'origin_currency_id' => [
                'required',
                'string',
                $this->transferCurrencyExists($currentCurrencyIds),
                Rule::in($this->transferAccount($team, $input['origin_account_id'] ?? null)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'origin_fee' => [
                'sometimes',
                'nullable',
                $this->transferDecimalRule($input['origin_currency_id'] ?? null),
                'gt:0',
            ],
            'origin_quote' => [
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
                    ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereIn(new Tag()->getKeyName(), $attachedTagIds)),
            ],
        ];
    }

    /**
     * Get one of the accounts of the team by its key, if any.
     */
    protected function transferAccount(Team $team, mixed $accountId): ?Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = is_string($accountId) || is_int($accountId)
            ? $team->accounts()->find($accountId)
            : null;

        return $account;
    }

    /**
     * Get a currency by its key, if any.
     */
    protected function transferCurrency(mixed $currencyId): ?Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = is_string($currencyId) || is_int($currencyId)
            ? Currency::query()->find($currencyId)
            : null;

        return $currency;
    }

    /**
     * Get the decimal rule of an amount held in the given
     * currency.
     */
    protected function transferDecimalRule(mixed $currencyId): string
    {
        $currency = $this->transferCurrency($currencyId);

        return $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18';
    }

    /**
     * Rule matching an account of the team that is active, or that
     * the transfer already sits on.
     *
     * @param  array<array-key, mixed>  $currentAccountIds
     */
    protected function transferAccountExists(Team $team, array $currentAccountIds): Exists
    {
        return Rule::exists(Account::class, new Account()->getKeyName())
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereIn(new Account()->getKeyName(), $currentAccountIds));
    }

    /**
     * Rule matching a currency that is active, or that the transfer
     * already holds one of its legs in.
     *
     * @param  array<array-key, mixed>  $currentCurrencyIds
     */
    protected function transferCurrencyExists(array $currentCurrencyIds): Exists
    {
        return Rule::exists(Currency::class, new Currency()->getKeyName())
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereIn(new Currency()->getKeyName(), $currentCurrencyIds));
    }
}
