<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Catalog\Models;

use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Catalog\Models\Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Catalog\Models\Currency>
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => Str::upper(fake()->unique()->currencyCode()),
            'decimal_places' => fake()->numberBetween(0, 18),
            'is_active' => fake()->boolean(),
            'name' => fake()->word(),
            'symbol' => fake()->randomLetter(),
        ];
    }
}
