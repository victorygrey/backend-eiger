<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['table_expedition_items', 'led_ambience_items'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->where('is_active', true)
                ->whereNotExists(function ($rfidQuery) use ($table): void {
                    $rfidQuery->selectRaw('1')
                        ->from('rfid_tags')
                        ->whereNotNull('rfid_tags.product_id')
                        ->whereColumn('rfid_tags.product_id', "{$table}.product_id")
                        ->whereRaw(
                            "UPPER(REPLACE(REPLACE(REPLACE(rfid_tags.uid, '-', ''), ':', ''), ' ', '')) = "
                            ."UPPER(REPLACE(REPLACE(REPLACE({$table}.rfid_tag, '-', ''), ':', ''), ' ', ''))",
                        );
                })
                ->update([
                    'is_active' => false,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // The previous active state cannot be reconstructed safely because the
        // master RFID assignment may have changed after this cleanup.
    }
};
