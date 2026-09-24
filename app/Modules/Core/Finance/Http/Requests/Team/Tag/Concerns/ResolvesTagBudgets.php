<?php

declare(strict_types=1);

namespace App\Modules\Core\Finance\Http\Requests\Team\Tag\Concerns;

use App\Modules\Core\Identity\Models\Team;
use App\Modules\Support\Helpers\Amount;

/**
 * Reads the quick budget amounts the tag form submits, one per
 * currency the team holds, and keeps them off a plan that does
 * not include budgets.
 */
trait ResolvesTagBudgets
{
    use ValidatesTagInputs;

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
        return $this->budgetsFrom($team, $this->validated('budgets'));
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
}
