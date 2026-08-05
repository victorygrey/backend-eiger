<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters here:
     * 1. Zones must exist before Products (FK: zone_id)
     * 2. Products must exist before RfidTags (FK: product_id)
     * 3. PrintRules and SyncLogs are independent
     */
    public function run(): void
    {
        $this->call([
            ZoneSeeder::class,
            ProductSeeder::class,
            RfidTagSeeder::class,
            PrintRuleSeeder::class,
            SyncLogSeeder::class,
        ]);
    }
}
