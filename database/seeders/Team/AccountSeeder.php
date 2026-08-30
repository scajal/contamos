<?php

declare(strict_types=1);

namespace Database\Seeders\Team;

use App\Modules\Core\Finance\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Account::factory(10)->create();
    }
}
