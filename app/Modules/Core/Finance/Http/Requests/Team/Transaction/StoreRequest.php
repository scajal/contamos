<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Http\Requests\Team\Transaction\Concerns\ResolvesTransactionInputs;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use App\Modules\Support\Helpers\Instant;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ResolvesTransactionInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => new Amount()->normalize($this->input('amount')),
            'quote' => new Amount()->normalize($this->input('quote')),
        ]);

        $this->merge([
            'occurred_at' => $occurredAt = Instant::toUtc($this->input('occurred_at'), $this->input('timezone')) ?? $this->input('occurred_at'),
            'quote' => ($quote = $this->input('quote')) ? $quote : Instant::resolveQuote($this->currency(), $occurredAt)?->rate,
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
                Rule::in(array_column(TransactionType::recordableCases(), 'value')),
            ],
        ];
    }
}
