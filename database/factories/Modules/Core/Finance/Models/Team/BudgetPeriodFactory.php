<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use App\Modules\Core\Finance\Models\Team\Tag;
use Carbon\CarbonImmutable;
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
     * A period belongs to a \App\Modules\Core\Finance\Models\Team\Tag
     * and is expressed in one
     * \App\Modules\Core\Catalog\Models\Currency. It runs from the
     * current month with no end, which is the ordinary budget.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 50, 5000),
            (new BudgetPeriod)->currency()->getForeignKeyName() => Currency::factory()->fiat(),
            'from_month' => CarbonImmutable::now()->startOfMonth(),
            (new BudgetPeriod)->tag()->getForeignKeyName() => Tag::factory(),
            'until_month' => null,
        ];
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
}
