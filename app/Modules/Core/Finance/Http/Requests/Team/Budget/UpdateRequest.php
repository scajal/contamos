<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Budget;

use App\Modules\Core\Finance\Http\Requests\Team\Budget\Concerns\ResolvesBudgetInputs;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    use ResolvesBudgetInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Budgets are an attribute of the team, so any member
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
     * What a period follows is fixed: moving a budget to
     * another category or tag is a new budget, not
     * an edit.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team, #[RouteParameter('budget')] BudgetPeriod $budget): array
    {
        return $this->budgetUpdateRules($team, $budget, $this->all());
    }
}
