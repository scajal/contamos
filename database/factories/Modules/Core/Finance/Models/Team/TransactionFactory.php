<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\TransactionType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Finance\Models\Team\Transaction;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Finance\Models\Team\Transaction>
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * Nested \App\Modules\Core\Finance\Models\Team\Account records belong to a
     * \App\Modules\Core\Identity\Models\Team, and `team_id` is copied from that account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->for(Team::factory()),
            'amount' => (string) fake()->randomFloat(18, 0, 999999999999999999),
            'currency_id' => Currency::factory(),
            'decimal_places' => fake()->numberBetween(0, 18),
            'description' => fake()->sentence(),
            'occurred_at' => fake()->dateTime(),
            'quote' => '1',
            'team_id' => $this->teamIdForAccount(...),
            'type' => fake()->randomElement(TransactionType::cases()),
            'user_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the transaction type is
     * \App\Modules\Core\Finance\Enums\Transaction\TransactionType::Expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Expense,
        ]);
    }

    /**
     * Indicate that the transaction type is
     * \App\Modules\Core\Finance\Enums\Transaction\TransactionType::Income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::Income,
        ]);
    }

    /**
     * Copy the team key from the nested \App\Modules\Core\Finance\Models\Team\Account.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function teamIdForAccount(array $attributes): string
    {
        $accountId = $attributes['account_id'] ?? null;

        if (! is_string($accountId)) {
            throw new \InvalidArgumentException('The transaction factory requires a string account id.');
        }

        $account = Account::query()->findOrFail($accountId);
        $teamId = $account->{$account->team()->getForeignKeyName()};

        if (! is_string($teamId)) {
            throw new \InvalidArgumentException('The account team foreign key must be a string.');
        }

        return $teamId;
    }
}
