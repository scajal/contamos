<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Catalog\Enums\Currency\CurrencyType;
use App\Modules\Core\Catalog\Enums\Currency\Quote\QuoteSource;
use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Currency::query()->create([
            'code' => 'UYU',
            'decimal_places' => 2,
            'name' => 'Peso Uruguayo',
            'quote_source' => QuoteSource::ManualOrScheduled,
            'symbol' => '$',
            'type' => CurrencyType::Fiat,
        ]);
    }
}
