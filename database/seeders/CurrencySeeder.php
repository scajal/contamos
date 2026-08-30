<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Catalog\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::factory(10)->create();
    }
}
