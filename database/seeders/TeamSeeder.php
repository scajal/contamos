<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Identity\Models\Team;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Team::factory(1)->create();
    }
}
