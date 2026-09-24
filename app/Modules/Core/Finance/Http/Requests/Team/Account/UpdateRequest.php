<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\ResolvesOpeningBalances;
use App\Modules\Core\Finance\Models\Team\Account;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use ResolvesOpeningBalances;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // @todo: Add authorization logic.
        return true;
    }

    /**
     * Get the currency ids being newly attached to the account,
     * i.e. the submitted currency ids that are not currently
     * attached to it.
     *
     * @return array<int, string>
     */
    protected function newlyAttachedCurrencyIds(Account $account): array
    {
        if (! is_array($currencyIds = $this->input('currency_ids', []))) {
            return [];
        }

        /** @var array<int, string> $attached */
        $attached = array_filter($account->currencies->pluck(new Currency()->getKeyName())->all(), is_string(...));

        return array_values(array_diff(array_filter($currencyIds, is_string(...)), $attached));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('account')] Account $account): array
    {
        return $this->rulesAcceptingBalancesFor($this->newlyAttachedCurrencyIds($account));
    }
}
