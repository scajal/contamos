<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Tag;

use App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns\ResolvesTagBudgets;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Closure;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
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
    public function rules(#[RouteParameter('team')] Team $team, #[RouteParameter('tag')] Tag $tag): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->uniqueName($team, $tag),
            ],
            ...$this->budgetRules($team),
        ];
    }

    /**
     * Reject a name that another tag on the team already uses, ignoring case.
     */
    protected function uniqueName(Team $team, Tag $except): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team, $except): void {
            // Validate that the value is a string.
            if (! is_string($value)) {
                return;
            }

            // Build the query to check if the name is unique.
            $taken = Tag::query()
                ->where((new Tag)->team()->getForeignKeyName(), $team->getKey())
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                ->whereKeyNot($except)
                ->exists();

            // If the tag name is not unique, fail the validation.
            if ($taken) {
                $fail(trans('validation.unique'));
            }
        };
    }
}
