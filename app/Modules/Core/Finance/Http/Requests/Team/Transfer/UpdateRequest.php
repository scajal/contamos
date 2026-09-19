<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Transfer;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Http\Requests\Team\Transfer\Concerns\ResolvesTransferInputs;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class UpdateRequest extends FormRequest
{
    use ResolvesTransferInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team, #[RouteParameter('transfer')] Transfer $transfer): array
    {
        $transfer->load('tags', 'transactions');

        // Keys the transfer already points at, which stay valid even
        // if they have since been deactivated.
        $attachedTagIds = $transfer->tags->pluck(new Tag()->getKeyName())->all();
        $currentAccountIds = $transfer->transactions->pluck(new Transaction()->account()->getForeignKeyName())->unique()->all();
        $currentCurrencyIds = $transfer->transactions->pluck(new Transaction()->currency()->getForeignKeyName())->unique()->all();

        return [
            'amount_received' => [
                'required',
                $this->decimalRuleFor('destination_currency_id'),
                'gt:0',
            ],
            'amount_sent' => [
                'required',
                $this->decimalRuleFor('origin_currency_id'),
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
                $this->accountExists($team, $currentAccountIds),
            ],
            'destination_currency_id' => [
                'required',
                'string',
                $this->currencyExists($currentCurrencyIds),
                Rule::in($this->destinationAccount($team)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'destination_fee' => [
                'sometimes',
                'nullable',
                $this->decimalRuleFor('destination_currency_id'),
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
                $this->accountExists($team, $currentAccountIds),
            ],
            'origin_currency_id' => [
                'required',
                'string',
                $this->currencyExists($currentCurrencyIds),
                Rule::in($this->originAccount($team)?->currencies->pluck(new Currency()->getKeyName()) ?? []),
            ],
            'origin_fee' => [
                'sometimes',
                'nullable',
                $this->decimalRuleFor('origin_currency_id'),
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
     * Rule matching an account of the team that is active, or that the
     * transfer already sits on.
     *
     * @param  array<array-key, mixed>  $currentAccountIds
     */
    protected function accountExists(Team $team, array $currentAccountIds): Exists
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
    protected function currencyExists(array $currentCurrencyIds): Exists
    {
        return Rule::exists(Currency::class, new Currency()->getKeyName())
            ->where(fn (Builder $query): Builder => $query->where('is_active', true)->orWhereIn(new Currency()->getKeyName(), $currentCurrencyIds));
    }
}
