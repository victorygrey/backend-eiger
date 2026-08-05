<?php

namespace Database\Seeders;

use App\Models\SyncLog;
use Illuminate\Database\Seeder;

class SyncLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 5 dummy sync log records for development/testing.
     */
    public function run(): void
    {
        SyncLog::factory()->count(5)->create();
    }
}
