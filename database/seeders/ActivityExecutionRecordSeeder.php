<?php

namespace Database\Seeders;

use App\Models\ActivityExecutionRecord;
use Illuminate\Database\Seeder;

class ActivityExecutionRecordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ActivityExecutionRecord::factory()->count(5)->create();
    }
}
