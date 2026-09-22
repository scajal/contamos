<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Validation\Rule;

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
     * Get the rules for the category, which every movement
     * recorded by hand carries, restricted to the list its
     * type offers.
     *
     * @return array<int, \Illuminate\Validation\Rules\Enum|string>
     */
    protected function categoryRules(): array
    {
        $type = $this->submittedType();

        $cases = $type instanceof TransactionType ? Category::casesFor($type) : [];

        // A type that is never recorded by hand, such as an opening
        // balance, carries no category at all.
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
     * Get the type the request is recording the movement with.
     */
    protected function submittedType(): ?TransactionType
    {
        $type = $this->input('type');

        return is_string($type) ? TransactionType::tryFrom($type) : null;
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
