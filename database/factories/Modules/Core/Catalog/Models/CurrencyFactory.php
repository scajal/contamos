<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Catalog\Models;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
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
            'quote_source' => fake()->randomElement(QuoteSource::cases()),
            'symbol' => fake()->randomLetter(),
            'type' => fake()->randomElement(CurrencyType::cases()),
        ];
    }

    /**
     * Indicate that the currency is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the currency type is
     * \App\Modules\Core\Catalog\Enums\Currency\CurrencyType::Cryptocurrency.
     */
    public function crypto(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CurrencyType::Cryptocurrency,
        ]);
    }

    /**
     * Indicate that the currency type is
     * \App\Modules\Core\Catalog\Enums\Currency\CurrencyType::Fiat.
     */
    public function fiat(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CurrencyType::Fiat,
        ]);
    }

    /**
     * Indicate that the quote source is
     * \App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource::Scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'quote_source' => QuoteSource::Scheduled,
        ]);
    }

    /**
     * Indicate that the currency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the currency type is
     * \App\Modules\Core\Catalog\Enums\Currency\CurrencyType::Virtual.
     */
    public function virtual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CurrencyType::Virtual,
        ]);
    }
}
