<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Reads the quick budget amounts the tag form submits, one per
 * currency the team holds, and keeps them off a plan that does
 * not include budgets.
 */
trait ResolvesTagBudgets
{
    /**
     * The submitted amounts keyed by currency, with the blanks
     * kept so clearing one still stops its budget. A team
     * whose plan has no budgets submits none, and a
     * currency the form left out keeps whatever
     * budget it already had.
     *
     * @return array<string, string|null>
     */
    public function budgets(Team $team): array
    {
        $submitted = $this->validated('budgets');

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

    /**
     * Normalise every submitted amount to the notation the
     * decimal rule accepts, the way a single amount field
     * is normalised.
     */
    protected function normalizeBudgets(): void
    {
        $submitted = $this->input('budgets');

        if (! is_array($submitted)) {
            return;
        }

        $this->merge([
            'budgets' => array_map(fn (mixed $amount): mixed => new Amount()->normalize($amount), $submitted),
        ]);
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
}
