<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use App\Modules\Core\Identity\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
 */
class BudgetPeriodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Finance\Models\Team\BudgetPeriod>
     */
    protected $model = BudgetPeriod::class;

    /**
     * Define the model's default state.
     *
     * A period belongs to a \App\Modules\Core\Identity\Models\Team,
     * follows one of the team's
     * \App\Modules\Core\Finance\Models\Team\Tag records and is
     * expressed in one \App\Modules\Core\Catalog\Models\Currency.
     * It runs from the current month with no end, which is
     * the ordinary budget.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = new BudgetPeriod;

        return [
            'amount' => fake()->randomFloat(2, 50, 5000),
            'category' => null,
            $period->currency()->getForeignKeyName() => Currency::factory()->fiat(),
            'from_month' => CarbonImmutable::now()->startOfMonth(),
            $period->team()->getForeignKeyName() => Team::factory(),
            $period->tag()->getForeignKeyName() => $this->tagOfTeam(),
            'until_month' => null,
            'warning_threshold' => null,
        ];
    }

    /**
     * Indicate that the period caps the given category, which
     * is a budget on an area of spending rather than on a
     * marker somebody has to attach by hand.
     */
    public function forCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'category' => $category ?? fake()->randomElement(Category::cases()),
            new BudgetPeriod()->tag()->getForeignKeyName() => null,
        ]);
    }

    /**
     * Indicate that the period follows the given tag, or a
     * fresh tag of the period's own team.
     */
    public function forTag(?Tag $tag = null): static
    {
        $period = new BudgetPeriod;

        // A tag handed in brings the team it belongs to, so
        // both sides keep pointing at one team.
        if ($tag instanceof Tag) {
            return $this->state([
                'category' => null,
                $period->tag()->getForeignKeyName() => $tag->getKey(),
                $period->team()->getForeignKeyName() => $tag->getAttribute($tag->team()->getForeignKeyName()),
            ]);
        }

        return $this->state([
            'category' => null,
            $period->tag()->getForeignKeyName() => $this->tagOfTeam(),
        ]);
    }

    /**
     * Indicate that the period is a sub-budget of the given
     * category period: a tag budget of the parent's own team
     * and currency, on a fresh tag of that team.
     */
    public function within(BudgetPeriod $parent): static
    {
        $period = new BudgetPeriod;

        return $this->state([
            'category' => null,
            $period->currency()->getForeignKeyName() => $parent->getAttribute($period->currency()->getForeignKeyName()),
            $period->parent()->getForeignKeyName() => $parent->getKey(),
            $period->tag()->getForeignKeyName() => $this->tagOfTeam(),
            $period->team()->getForeignKeyName() => $parent->getAttribute($period->team()->getForeignKeyName()),
        ]);
    }

    /**
     * Indicate that the period ran its course before the
     * current month because another period took over.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_month' => CarbonImmutable::now()->startOfMonth()->subMonths(3),
            'until_month' => CarbonImmutable::now()->startOfMonth()->subMonth(),
        ]);
    }

    /**
     * Indicate that the period was stopped at the end of
     * last month and nothing followed it.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_month' => CarbonImmutable::now()->startOfMonth()->subMonths(6),
            'until_month' => CarbonImmutable::now()->startOfMonth()->subMonth(),
        ]);
    }

    /**
     * Indicate that the period applies in the
     * current month.
     */
    public function running(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_month' => CarbonImmutable::now()->startOfMonth(),
            'until_month' => null,
        ]);
    }

    /**
     * Indicate that the period starts in a
     * later month.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_month' => CarbonImmutable::now()->startOfMonth()->addMonth(),
            'until_month' => null,
        ]);
    }

    /**
     * Indicate that the period is a one-month objective on
     * the given month, the current one by default.
     */
    public function singleMonth(?CarbonImmutable $month = null): static
    {
        $month = ($month ?? CarbonImmutable::now())->startOfMonth();

        return $this->state(fn (array $attributes): array => [
            'from_month' => $month,
            'until_month' => $month,
        ]);
    }

    /**
     * Indicate that the team wants to be warned once the
     * month has used the given share of the budget.
     */
    public function withWarningThreshold(int $percent = 80): static
    {
        return $this->state([
            'warning_threshold' => $percent,
        ]);
    }

    /**
     * A tag of the team the period is being created for, so a
     * tag budget never points across two teams.
     *
     * @return \Closure(array<string, mixed>): string
     */
    protected function tagOfTeam(): Closure
    {
        $teamKey = new BudgetPeriod()->team()->getForeignKeyName();

        return function (array $attributes) use ($teamKey): string {
            /** @var \App\Modules\Core\Finance\Models\Team\Tag $tag */
            $tag = Tag::factory()->create([$teamKey => $attributes[$teamKey]]);

            return (string) $tag->getKey();
        };
    }
}
