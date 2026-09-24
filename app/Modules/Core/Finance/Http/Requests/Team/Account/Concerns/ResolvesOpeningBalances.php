<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns;

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Support\Helpers\Instant;
use Carbon\CarbonImmutable;

/**
 * Shared opening-balance validation and resolution for the account
 * Store and Update requests, reading the submitted input into the
 * plain-value rules of
 * \App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\DefinesAccountRules.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesOpeningBalances
{
    use DefinesAccountRules;

    /**
     * The selected account type.
     */
    protected function accountType(): ?AccountType
    {
        return AccountType::tryFrom($this->string('type')->toString());
    }

    /**
     * Get the submitted rates, keyed by currency id.
     *
     * @return array<array-key, mixed>
     */
    protected function submittedQuotes(): array
    {
        return is_array($quotes = $this->input('quotes', [])) ? $quotes : [];
    }

    /**
     * Get the rules for the submitted account, accepting opening
     * balances for the given currencies.
     *
     * @param  array<int, string>  $balanceCurrencyIds
     * @return array<string, array<mixed>>
     */
    protected function rulesAcceptingBalancesFor(array $balanceCurrencyIds): array
    {
        return $this->accountRules($this->accountType(), $balanceCurrencyIds, $this->submittedQuotes(), $this->input('occurred_at'));
    }

    /**
     * Get the opening balances to create, keyed by currency id,
     * for currencies with a positive amount and a resolvable
     * quote, catalog or user-typed.
     *
     * @return array<string, \App\Modules\Core\Finance\Objects\OpeningBalance>
     */
    public function openingBalances(): array
    {
        /** @var array<array-key, mixed> $balances */
        $balances = $this->safe(['opening_balances'])['opening_balances'] ?? [];

        return $this->resolveOpeningBalances($balances, $this->submittedQuotes(), $this->safe(['occurred_at'])['occurred_at'] ?? null);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'occurred_at' => Instant::toUtc($this->input('occurred_at'), $this->input('timezone'))
                ?? $this->input('occurred_at')
                ?? CarbonImmutable::now()->toIso8601String(),
        ]);
    }
}
