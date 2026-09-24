<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * The rules a tag is written with, whoever writes it: a name no
 * other tag of the team uses, and the quick budget amounts, one
 * per currency the team holds. Every method takes plain values,
 * so the web form and the MCP tools validate alike.
 */
trait ValidatesTagInputs
{
    /**
     * The rules the tag's name is validated with, ignoring the
     * tag being edited, if any.
     *
     * @return array<int, \Closure|string>
     */
    protected function tagNameRules(Team $team, ?Tag $except = null): array
    {
        return [
            'required',
            'string',
            'max:255',
            $this->uniqueTagName($team, $except),
        ];
    }

    /**
     * Reject a name that another tag on the team already uses,
     * ignoring case.
     */
    protected function uniqueTagName(Team $team, ?Tag $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team, $except): void {
            // Anything but a string is another rule's to report.
            if (! is_string($value)) {
                return;
            }

            // Look for another tag of the team with the name.
            $taken = Tag::query()
                ->where(new Tag()->team()->getForeignKeyName(), $team->getKey())
                ->whereRaw('LOWER(name) = ?', [Str::lower($value)])
                ->when($except instanceof Tag, fn ($query) => $query->whereKeyNot($except))
                ->exists();

            // If the tag name is not unique, fail the validation.
            if ($taken) {
                $fail(trans('validation.unique'));
            }
        };
    }

    /**
     * The rules the quick amounts are validated with: a key per
     * currency the team holds on an active account, each an
     * optional positive amount. A plan without budgets has
     * no rules because it has no fields.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function budgetRules(Team $team): array
    {
        if (! Feature::ManageBudgets->enabled($team)) {
            return [];
        }

        $rules = [
            'budgets' => [
                'sometimes',
                'array',
                $this->heldCurrencyKeys($team),
            ],
        ];

        foreach ($team->heldCurrencies() as $currency) {
            $rules['budgets.'.$currency->getKey()] = [
                'sometimes',
                'nullable',
                "decimal:0,{$currency->decimal_places}",
                'gt:0',
            ];
        }

        return $rules;
    }

    /**
     * Refuse an amount keyed by a currency the team does not
     * hold on an active account, since such a budget could
     * never show progress.
     */
    protected function heldCurrencyKeys(Team $team): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($team): void {
            if (! is_array($value)) {
                return;
            }

            $held = $team->heldCurrencies()
                ->pluck(new Currency()->getKeyName())
                ->all();

            foreach (array_keys($value) as $currencyId) {
                if (! in_array((string) $currencyId, $held, true)) {
                    $fail(trans('Your team does not hold this currency on an active account.'));

                    return;
                }
            }
        };
    }

    /**
     * The validated amounts keyed by currency, with the blanks
     * kept so clearing one still stops its budget. A team
     * whose plan has no budgets gets none, and a currency
     * left out keeps whatever budget it already had.
     *
     * @return array<string, string|null>
     */
    protected function budgetsFrom(Team $team, mixed $submitted): array
    {
        if (! Feature::ManageBudgets->enabled($team) || ! is_array($submitted)) {
            return [];
        }

        $budgets = [];

        foreach ($team->heldCurrencies() as $currency) {
            $key = (string) $currency->getKey();

            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            $amount = $submitted[$key];

            $budgets[$key] = is_scalar($amount) && filled($amount) ? (string) $amount : null;
        }

        return $budgets;
    }
}
