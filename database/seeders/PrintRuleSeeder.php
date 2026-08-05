<?php

namespace Database\Seeders;

use App\Models\PrintRule;
use Illuminate\Database\Seeder;

class PrintRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a single default print rule configuration.
     */
    public function run(): void
    {
        PrintRule::factory()->create();
    }
}
