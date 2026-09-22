<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Finance\Enums\Transaction\Category;
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
            'category' => $this->categoryForType(...),
            'currency_id' => Currency::factory(),
            'decimal_places' => fake()->numberBetween(0, 18),
            'description' => fake()->sentence(),
            // Recent, so a transaction is inside the history window
            // the free plan keeps visible. A test that cares about
            // the window sets the date itself.
            'occurred_at' => fake()->dateTimeBetween('-3 months'),
            'quote' => '1',
            'team_id' => $this->teamIdForAccount(...),
            'type' => fake()->randomElement([TransactionType::Expense, TransactionType::Income]),
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
     * Indicate that the transaction type is
     * \App\Modules\Core\Finance\Enums\Transaction\TransactionType::OpeningBalance.
     */
    public function openingBalance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::OpeningBalance,
        ]);
    }

    /**
     * Indicate that the transaction type is
     * \App\Modules\Core\Finance\Enums\Transaction\TransactionType::TransferIn.
     */
    public function transferIn(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::TransferIn,
        ]);
    }

    /**
     * Indicate that the transaction type is
     * \App\Modules\Core\Finance\Enums\Transaction\TransactionType::TransferOut.
     */
    public function transferOut(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => TransactionType::TransferOut,
        ]);
    }

    /**
     * Pick a category the drawn type can carry, and none at all for
     * the types that never carry one, such as the legs of a
     * transfer and an opening balance.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function categoryForType(array $attributes): ?Category
    {
        $type = $attributes['type'] ?? null;

        // The state may have set the type as the enum itself
        // or as its backing value.
        $type = $type instanceof TransactionType ? $type : TransactionType::tryFrom(is_string($type) ? $type : '');

        $cases = $type instanceof TransactionType ? Category::casesFor($type) : [];

        return $cases === [] ? null : $cases[array_rand($cases)];
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
