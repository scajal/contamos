<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Account;

use App\Modules\Core\Finance\Http\Requests\Team\Account\Concerns\ResolvesOpeningBalances;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
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
     * Get the submitted currency ids.
     *
     * @return array<int, string>
     */
    protected function currencyIds(): array
    {
        $currencyIds = $this->input('currency_ids', []);

        if (! is_array($currencyIds)) {
            return [];
        }

        return array_values(array_filter($currencyIds, is_string(...)));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->rulesAcceptingBalancesFor($this->currencyIds());
    }
}
