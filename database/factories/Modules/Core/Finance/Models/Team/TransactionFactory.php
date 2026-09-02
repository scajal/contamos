<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

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
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'amount' => (string) fake()->randomFloat(18, 0, 999999999999999999),
            'description' => fake()->sentence(),
            'occurred_at' => fake()->dateTime(),
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
        ];
    }
}
