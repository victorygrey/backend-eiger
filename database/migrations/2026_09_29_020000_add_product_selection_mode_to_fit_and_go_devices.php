<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fit_and_go_devices', function (Blueprint $table) {
            $table->string('product_selection_mode', 20)->default('selected')->after('is_active');
        });

        DB::table('fit_and_go_categories')->updateOrInsert(
            ['code' => 'bags'],
            [
                'name' => 'Bags',
                'display_name' => 'Tas',
                'mc_level' => 'mc 3',
                'mc_keywords' => 'bag, backpack, rucksack, duffel, carrier, pouch, tas',
                'icon' => 'bi-handbag',
                'sort_order' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('fit_and_go_categories')->where('code', 'bags')->delete();

        Schema::table('fit_and_go_devices', function (Blueprint $table) {
            $table->dropColumn('product_selection_mode');
        });
    }
};
