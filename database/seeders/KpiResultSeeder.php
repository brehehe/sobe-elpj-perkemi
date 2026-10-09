<?php

namespace Database\Seeders;

use App\Models\KpiResult;
use Illuminate\Database\Seeder;

class KpiResultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KpiResult::factory()->count(3)->create();
    }
}
