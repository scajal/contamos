<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Enums\BudgetPeriod\BudgetAlertKind;
use App\Modules\Core\Finance\Models\Team\BudgetAlert;
use App\Modules\Core\Finance\Models\Team\BudgetPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\BudgetAlert>
 */
class BudgetAlertFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Finance\Models\Team\BudgetAlert>
     */
    protected $model = BudgetAlert::class;

    /**
     * Define the model's default state.
     *
     * An alert records that a
     * \App\Modules\Core\Finance\Models\Team\BudgetPeriod
     * warned the team in the current month.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            new BudgetAlert()->budgetPeriod()->getForeignKeyName() => BudgetPeriod::factory(),
            'kind' => BudgetAlertKind::Warning,
            'month' => CarbonImmutable::now()->startOfMonth(),
        ];
    }

    /**
     * Indicate that the alert said the budget went over.
     */
    public function over(): static
    {
        return $this->state([
            'kind' => BudgetAlertKind::Over,
        ]);
    }
}
