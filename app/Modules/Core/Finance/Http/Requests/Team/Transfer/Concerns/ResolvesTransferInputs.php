<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Instant;

/**
 * Shared per-side account, currency, and tag id resolution for the
 * transfer Store and Update requests.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesTransferInputs
{
    /**
     * Get the account of one of the sides of the
     * transfer, if any.
     */
    protected function accountFor(Team $team, string $key): ?Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = Account::query()
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->find($this->input($key));

        return $account;
    }

    /**
     * Get the currency of one of the sides of the
     * transfer, if any.
     */
    protected function currencyFor(string $key): ?Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()
            ->find($this->input($key));

        return $currency;
    }

    /**
     * Get the decimal rule of one of the sides of
     * the transfer.
     */
    protected function decimalRuleFor(string $key): string
    {
        $currency = $this->currencyFor($key);

        return $currency instanceof Currency ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18';
    }

    /**
     * Get the account the transfer leaves from, if any.
     */
    protected function destinationAccount(Team $team): ?Account
    {
        return $this->accountFor($team, 'destination_account_id');
    }

    /**
     * Get the currency the transfer arrives in, if any.
     */
    protected function destinationCurrency(): ?Currency
    {
        return $this->currencyFor('destination_currency_id');
    }

    /**
     * Get the account the transfer leaves from, if any.
     */
    protected function originAccount(Team $team): ?Account
    {
        return $this->accountFor($team, 'origin_account_id');
    }

    /**
     * Get the currency the transfer leaves in, if any.
     */
    protected function originCurrency(): ?Currency
    {
        return $this->currencyFor('origin_currency_id');
    }

    /**
     * Prepare the data for validation, converting the submitted
     * instant to UTC and defaulting each side's blank quote from
     * the rate as of that instant.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'occurred_at' => $occurredAt = Instant::toUtc($this->input('occurred_at'), $this->input('timezone')) ?? $this->input('occurred_at'),
            'destination_quote' => ($destinationQuote = $this->input('destination_quote')) ? $destinationQuote : Instant::resolveQuote($this->destinationCurrency(), $occurredAt)?->rate,
            'origin_quote' => ($originQuote = $this->input('origin_quote')) ? $originQuote : Instant::resolveQuote($this->originCurrency(), $occurredAt)?->rate,
        ]);
    }

    /**
     * Get the validated tag ids for the transfer.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        if (! is_array($tagIds = $this->safe(['tag_ids'])['tag_ids'] ?? [])) {
            return [];
        }

        return array_values(array_filter($tagIds, is_string(...)));
    }
}
