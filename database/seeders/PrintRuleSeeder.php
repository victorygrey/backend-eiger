<?php

namespace Database\Seeders;

use App\Models\PrintRule;
use Illuminate\Database\Seeder;

class PrintRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates a single default print rule configuration without Faker dependency.
     */
    public function run(): void
    {
        PrintRule::firstOrCreate(
            ['id' => 1],
            [
                'minimum_transaction' => 500000,
                'require_membership'  => false,
                'enabled'             => true,
            ]
        );
    }
}
