<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        $this->backfillAiFitAndGoProducts();
        $this->backfillInteractiveTabletProducts();
    }

    public function down(): void
    {
        // Channel choices may have been changed by users after this migration.
        // Reverting the inferred flags would destroy those later choices.
    }

    private function backfillAiFitAndGoProducts(): void
    {
        $mappingTables = [
            'fit_and_go_item_visibilities',
            'fit_and_go_activity_products',
            'fit_and_go_device_activity_products',
        ];

        foreach ($mappingTables as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'product_id')) {
                continue;
            }

            $mappedProductIds = DB::table($table)
                ->select('product_id')
                ->whereNotNull('product_id');

            if (Schema::hasColumn($table, 'is_visible')) {
                $mappedProductIds->where('is_visible', true);
            }

            DB::table('products')
                ->whereIn('id', $mappedProductIds)
                ->update(['ai_fit_and_go_active' => true]);
        }
    }

    private function backfillInteractiveTabletProducts(): void
    {
        if (Schema::hasTable('tablets') && Schema::hasColumn('tablets', 'featured_product_id')) {
            DB::table('products')
                ->whereIn('id', DB::table('tablets')
                    ->select('featured_product_id')
                    ->whereNotNull('featured_product_id'))
                ->update(['interactive_tablet_active' => true]);
        }

        if (Schema::hasTable('tablet_recommendations')
            && Schema::hasColumn('tablet_recommendations', 'product_id')) {
            DB::table('products')
                ->whereIn('id', DB::table('tablet_recommendations')
                    ->select('product_id')
                    ->whereNotNull('product_id'))
                ->update(['interactive_tablet_active' => true]);
        }
    }
};
