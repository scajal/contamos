<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\RecurringRule\Frequency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\RecurringRule;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\RecurringRule>
 */
class RecurringRuleFactory extends Factory
{
    protected $model = RecurringRule::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->for(Team::factory()),
            'amount' => (string) fake()->randomFloat(2, 1, 9999),
            'currency_id' => Currency::factory(),
            'decimal_places' => 2,
            'description' => fake()->sentence(),
            'ends_on' => null,
            'frequency' => Frequency::Monthly,
            'interval' => 1,
            'is_active' => true,
            'last_generated_due_date' => null,
            'starts_on' => fake()->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'team_id' => $this->teamIdForAccount(...),
            'time_of_day' => '09:00:00',
            'timezone' => 'UTC',
            'type' => fake()->randomElement([TransactionType::Expense, TransactionType::Income]),
            'user_id' => User::factory(),
        ];
    }

    /**
     * A rule whose last run could not generate its occurrence.
     */
    public function failed(string $reason = 'No rate for the due date.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'failed_at' => CarbonImmutable::now(),
            'failure_reason' => $reason,
        ]);
    }

    /**
     * A rule a member has suspended.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Expense]);
    }

    public function income(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => TransactionType::Income]);
    }

    /**
     * Resolve the team the chosen account belongs to.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function teamIdForAccount(array $attributes): string
    {
        $accountId = $attributes['account_id'] ?? null;

        if (! is_string($accountId)) {
            throw new \InvalidArgumentException('The recurring rule factory requires a string account id.');
        }

        $account = Account::query()->findOrFail($accountId);
        $teamId = $account->{$account->team()->getForeignKeyName()};

        if (! is_string($teamId)) {
            throw new \InvalidArgumentException('The account team foreign key must be a string.');
        }

        return $teamId;
    }
}
