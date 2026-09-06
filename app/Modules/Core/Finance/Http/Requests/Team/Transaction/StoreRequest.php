<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
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
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
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
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'quote' => ($quote = $this->input('quote')) ? $quote : $this->currency()?->currentQuote?->rate,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            new Transaction()->account()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Account::class, new Account()->getKeyName())
                    ->where(new Account()->team()->getForeignKeyName(), $team->getKey()),
            ],
            'amount' => [
                'required',
                (($currency = $this->currency()) instanceof \App\Modules\Core\Catalog\Models\Currency) ? "decimal:0,{$currency->decimal_places}" : 'decimal:0,18',
                'gt:0',
            ],
            new Transaction()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in($this->account($team)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
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
                    ->where('is_active', true),
            ],
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::enum(TransactionType::class),
            ],
        ];
    }

    /**
     * Get the validated tag ids for the transaction.
     *
     * @return array<int, string>
     */
    public function tagIds(): array
    {
        $tagIds = $this->safe(['tag_ids'])['tag_ids'] ?? [];

        if (! is_array($tagIds)) {
            return [];
        }

        return array_values(array_filter($tagIds, is_string(...)));
    }
}
