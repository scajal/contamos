<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Enums\Account\AccountType;
use App\Modules\Core\Finance\Models\Team\Account;
use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Finance\Models\Team\Account>
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * Each account belongs to a \App\Modules\Core\Identity\Models\Team.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_active' => fake()->boolean(),
            'name' => fake()->word(),
            (new Account)->team()->getForeignKeyName() => Team::factory(),
            'type' => fake()->randomElement(AccountType::cases()),
        ];
    }

    /**
     * Indicate that the account is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the account type is
     * \App\Modules\Core\Finance\Enums\Account\AccountType::Bank.
     */
    public function bank(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountType::Bank,
        ]);
    }

    /**
     * Indicate that the account type is
     * \App\Modules\Core\Finance\Enums\Account\AccountType::Cash.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountType::Cash,
        ]);
    }

    /**
     * Indicate that the account type is
     * \App\Modules\Core\Finance\Enums\Account\AccountType::Crypto.
     */
    public function crypto(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AccountType::Crypto,
        ]);
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }
}
