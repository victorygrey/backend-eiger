<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_activities', function (Blueprint $table): void {
            $table->decimal('selected_rating', 5, 2)->nullable()->after('is_selected');
        });

        Schema::table('product_performances', function (Blueprint $table): void {
            $table->decimal('selected_rating', 5, 2)->nullable()->after('is_selected');
        });

        DB::table('product_pim_records')->select(['id', 'product_id', 'product_payload'])
            ->orderBy('id')
            ->chunkById(100, function ($records): void {
                foreach ($records as $record) {
                    $payload = is_array($record->product_payload)
                        ? $record->product_payload
                        : json_decode((string) $record->product_payload, true);

                    if (! is_array($payload)) {
                        continue;
                    }

                    $this->backfillRatings('product_activities', $record->product_id, $payload['activity'] ?? []);
                    $this->backfillRatings('product_performances', $record->product_id, $payload['performance'] ?? []);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('product_performances', fn (Blueprint $table) => $table->dropColumn('selected_rating'));
        Schema::table('product_activities', fn (Blueprint $table) => $table->dropColumn('selected_rating'));
    }

    private function backfillRatings(string $table, int $productId, mixed $rows): void
    {
        if (! is_array($rows)) {
            return;
        }

        foreach (array_values($rows) as $position => $row) {
            $selected = is_array($row) ? ($row['selected'] ?? null) : null;
            if (! is_numeric($selected)) {
                continue;
            }

            DB::table($table)
                ->where('product_id', $productId)
                ->where('sort_order', $position)
                ->update(['selected_rating' => (float) $selected]);
        }
    }
};
