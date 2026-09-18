<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;

/**
 * Shared account, currency, and tag id resolution for the transaction
 * Store and Update requests.
 *
 * @mixin \Illuminate\Foundation\Http\FormRequest
 */
trait ResolvesTransactionInputs
{
    /**
     * Get the account for the transaction.
     */
    protected function account(Team $team): ?Account
    {
        /** @var \App\Modules\Core\Finance\Models\Team\Account|null $account */
        $account = Account::query()
            ->where(new Account()->team()->getForeignKeyName(), $team->getKey())
            ->find($this->input(new Transaction()->account()->getForeignKeyName()));

        return $account;
    }

    /**
     * Get the selected currency, if any.
     */
    protected function currency(): ?Currency
    {
        /** @var \App\Modules\Core\Catalog\Models\Currency|null $currency */
        $currency = Currency::query()
            ->find($this->input(new Transaction()->currency()->getForeignKeyName()));

        return $currency;
    }

    /**
     * Get the validated tag ids for the transaction.
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
