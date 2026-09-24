<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Tag;

use App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns\ResolvesTagBudgets;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    use ResolvesTagBudgets;

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
        $this->normalizeBudgets();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(#[RouteParameter('team')] Team $team): array
    {
        return [
            'name' => $this->tagNameRules($team),
            ...$this->budgetRules($team),
        ];
    }
}
