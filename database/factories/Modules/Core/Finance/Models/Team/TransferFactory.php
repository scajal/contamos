<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Finance\Models\Team;

use App\Modules\Core\Finance\Models\Team\Transfer;
use App\Modules\Core\Identity\Models\Team;
use App\Modules\Core\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Finance\Models\Team\Transfer>
 */
class TransferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Finance\Models\Team\Transfer>
     */
    protected $model = Transfer::class;

    /**
     * Define the model's default state.
     *
     * Each transfer belongs to a \App\Modules\Core\Identity\Models\Team and is
     * recorded by a \App\Modules\Core\Identity\Models\User.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'occurred_at' => fake()->dateTime(),
            (new Transfer)->team()->getForeignKeyName() => Team::factory(),
            (new Transfer)->user()->getForeignKeyName() => User::factory(),
        ];
    }
}
