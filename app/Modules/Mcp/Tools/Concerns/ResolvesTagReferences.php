<?php

declare(strict_types=1);

namespace App\Modules\Mcp\Tools\Concerns;

use App\Modules\Billing\Enums\Feature;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Mcp\Exceptions\ToolError;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Finds the team's tags the tag and budget tools name, by id or
 * by name, reads the quick budget amounts they are given per
 * currency code, and presents a tag the way the tools return
 * it.
 *
 * @mixin \Laravel\Mcp\Server\Tool
 */
trait ResolvesTagReferences
{
    /**
     * Get the schema of the `tag` argument naming one of the
     * team's tags.
     *
     * @return array{tag: \Illuminate\JsonSchema\Types\Type}
     */
    protected function tagSchema(JsonSchema $schema): array
    {
        return [
            'tag' => $schema->string()
                ->description("The tag's id, or its exact name (case does not matter), as listed by `list_tags`.")
                ->required(),
        ];
    }

    /**
     * Get the schema of the `budgets` argument holding the
     * tag's monthly budget per currency.
     *
     * @return array{budgets: \Illuminate\JsonSchema\Types\Type}
     */
    protected function tagBudgetsSchema(JsonSchema $schema): array
    {
        return [
            'budgets' => $schema->object()
                ->description('The monthly budget of the tag per currency, from this month on, keyed by ISO currency code, e.g. {"USD": "500", "UYU": "20000"}. Amounts are decimal strings; null stops that currency\'s budget. Currencies left out keep their budget. Needs a plan with budgets.'),
        ];
    }

    /**
     * Find one of the team's tags by id or by exact name,
     * ignoring case. A tag of another team reads as not
     * found.
     */
    protected function tagOf(Team $team, mixed $reference): Tag
    {
        $tag = null;

        // Match by id first, then by name.
        if (is_string($reference) && Str::trim($reference) !== '') {
            $tag = $team->tags()->whereKey($reference)->first()
                ?? $team->tags()->whereRaw('LOWER(name) = ?', [Str::lower(Str::trim($reference))])->first();
        }

        if (! $tag instanceof Tag) {
            throw new ToolError("Tag not found. Call `list_tags` to see the team's tags.");
        }

        return $tag;
    }

    /**
     * Turn budget amounts keyed by currency code into the
     * amounts keyed by currency the web form submits. A
     * code the team holds on no active account is refused
     * with the codes it does hold.
     *
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function budgetAmountsByCurrency(Team $team, mixed $budgets): array
    {
        if (! is_array($budgets)) {
            throw ValidationException::withMessages(['budgets' => 'The budgets must be an object keyed by currency code.']);
        }

        $held = $team->heldCurrencies();

        $amounts = [];

        foreach ($budgets as $code => $amount) {
            // The code must name a currency the team holds.
            $currency = $held->first(fn (Currency $currency): bool => Str::upper($currency->code) === Str::upper(Str::trim((string) $code)));

            if (! $currency instanceof Currency) {
                throw new ToolError("The team does not hold {$code} on an active account. Its currencies are: ".($held->pluck('code')->implode(', ') ?: 'none').'.');
            }

            // Numbers are read as the decimal they spell.
            $amounts[(string) $currency->getKey()] = is_int($amount) || is_float($amount) ? (string) $amount : $amount;
        }

        return $amounts;
    }

    /**
     * Present a tag, with the budget it runs on this month in
     * each currency when the team's plan has budgets.
     *
     * @return array<string, mixed>
     */
    protected function presentTag(Team $team, Tag $tag): array
    {
        $presented = [
            'id' => $tag->getKey(),
            'name' => $tag->name,
            'is_active' => $tag->is_active,
        ];

        if (Feature::ManageBudgets->enabled($team)) {
            // Only the periods in force this month apply.
            $presented['budgets'] = $tag->budgetPeriods()
                ->covering(CarbonImmutable::now())
                ->with('currency')
                ->get()
                ->map(fn (BudgetPeriod $period): array => [
                    'currency' => $period->currency->code,
                    'amount' => $this->amount($period->amount, $period->currency->decimal_places),
                ])
                ->sortBy('currency')
                ->values()
                ->all();
        }

        return $presented;
    }
}
