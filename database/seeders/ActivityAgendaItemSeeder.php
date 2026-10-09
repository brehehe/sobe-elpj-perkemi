<?php

namespace Database\Seeders;

use App\Models\ActivityAgendaItem;
use Illuminate\Database\Seeder;

class ActivityAgendaItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ActivityAgendaItem::factory()->count(5)->create();
    }
}
