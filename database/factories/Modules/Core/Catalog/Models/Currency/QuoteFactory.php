<?php

declare(strict_types=1);

namespace Database\Factories\Modules\Core\Catalog\Models\Currency;

use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use App\Modules\Core\Catalog\Models\Currency\Quote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Core\Catalog\Models\Currency\Quote>
 */
class QuoteFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Modules\Core\Catalog\Models\Currency\Quote>
     */
    protected $model = Quote::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'currency_id' => Currency::factory(),
            'observed_at' => fake()->dateTime(),
            'rate' => fake()->randomFloat(2, 0, 100),
            'recorded_by' => null,
            'source' => fake()->randomElement(QuoteSource::cases()),
        ];
    }
}
