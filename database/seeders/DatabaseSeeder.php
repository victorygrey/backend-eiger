<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * In production, only essential store structures (Zones and PrintRules) are seeded.
     * Dummy products and test records requiring Faker are restricted to local/testing environments.
     */
    public function run(): void
    {
        $this->call([
            ZoneSeeder::class,
            PrintRuleSeeder::class,
            FitAndGoSeeder::class,
        ]);

        if (app()->environment('local', 'testing') && class_exists(\Faker\Factory::class)) {
            $this->call([
                ProductSeeder::class,
                RfidTagSeeder::class,
                SyncLogSeeder::class,
                TabletSeeder::class,
            ]);
        }
    }
}
