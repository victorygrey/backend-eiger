<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('pim_id')->nullable()->index();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->boolean('is_selected')->default(false)->index();
            $table->decimal('rating', 5, 2)->nullable();
            $table->text('rating_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('product_pim_records')->orderBy('id')->chunkById(100, function ($records) use ($now): void {
            foreach ($records as $record) {
                $payload = json_decode((string) $record->product_payload, true);
                foreach (array_values(is_array($payload) ? ($payload['performance'] ?? []) : []) as $position => $row) {
                    if (! is_array($row) || trim((string) ($row['name'] ?? '')) === '') {
                        continue;
                    }
                    DB::table('product_performances')->insert([
                        'product_id' => $record->product_id,
                        'pim_id' => $row['id'] ?? null,
                        'name' => $row['name'],
                        'description' => $row['description'] ?? null,
                        'is_selected' => $this->selected($row['selected'] ?? false),
                        'rating' => is_numeric($row['rating'] ?? null) ? $row['rating'] : null,
                        'rating_description' => $row['desc_rating'] ?? $row['rating_desc'] ?? null,
                        'sort_order' => $position,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_performances');
    }

    private function selected(mixed $value): bool
    {
        if (is_numeric($value)) {
            return (float) $value > 0;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
};
