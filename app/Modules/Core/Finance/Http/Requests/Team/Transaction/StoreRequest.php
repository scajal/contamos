<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transaction;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
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
        return Currency::query()
            ->find($this->input(new Transaction()->currency()->getForeignKeyName()));
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
                ($currency = $this->currency()) ? "decimal:0,{$currency->decimal_places}" : 'integer',
                'gt:0',
            ],
            new Transaction()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                Rule::in(Account::query()->find($this->input(new Transaction()->account()->getForeignKeyName()))->currencies->pluck(new Currency()->getKeyName())),
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
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::enum(TransactionType::class),
            ],
        ];
    }
}
