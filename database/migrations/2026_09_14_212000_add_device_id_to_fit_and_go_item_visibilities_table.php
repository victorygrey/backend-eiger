<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        try {
            Schema::table('fit_and_go_item_visibilities', function (Blueprint $table) {
                $table->dropUnique('fit_and_go_item_unique');
            });
        } catch (\Throwable $e) {
            // Index might already be dropped or not exist
        }

        Schema::table('fit_and_go_item_visibilities', function (Blueprint $table) {
            if (!Schema::hasColumn('fit_and_go_item_visibilities', 'device_id')) {
                $table->foreignId('device_id')->nullable()->after('id')->constrained('fit_and_go_devices')->cascadeOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fit_and_go_item_visibilities', function (Blueprint $table) {
            if (Schema::hasColumn('fit_and_go_item_visibilities', 'device_id')) {
                $table->dropConstrainedForeignId('device_id');
            }
        });

        try {
            Schema::table('fit_and_go_item_visibilities', function (Blueprint $table) {
                $table->unique(['product_id', 'category_code'], 'fit_and_go_item_unique');
            });
        } catch (\Throwable $e) {
            // Ignore
        }
    }
};
