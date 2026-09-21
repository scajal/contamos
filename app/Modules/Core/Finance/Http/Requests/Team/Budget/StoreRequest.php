<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Budget;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns\ResolvesBudgetInputs;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    use ResolvesBudgetInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Budgets are an attribute of the tag, so any member
        // who may edit tags may set them.
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'amount' => new Amount()->normalize($this->input('amount')),
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
            'amount' => [
                'required',
                'decimal:0,18',
                'gt:0',
            ],
            new BudgetPeriod()->currency()->getForeignKeyName() => [
                'required',
                'string',
                Rule::exists(Currency::class, new Currency()->getKeyName()),
                $this->heldCurrency($team),
            ],
            'from_month' => [
                'required',
                'string',
                'date_format:'.self::MONTH_FORMAT,
                $this->freeMonths($team),
            ],
            new BudgetPeriod()->tag()->getForeignKeyName() => [
                'required',
                'string',
                $this->teamTag($team),
            ],
            'until_month' => [
                'sometimes',
                'nullable',
                'string',
                'date_format:'.self::MONTH_FORMAT,
            ],
        ];
    }
}
