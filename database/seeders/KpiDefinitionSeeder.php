<?php

namespace Database\Seeders;

use App\Models\KpiDefinition;
use Illuminate\Database\Seeder;

class KpiDefinitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        KpiDefinition::factory()->count(3)->create();
    }
}
