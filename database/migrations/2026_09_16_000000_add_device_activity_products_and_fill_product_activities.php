<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fit_and_go_device_activity_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained('fit_and_go_devices')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('fit_and_go_activities')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['device_id', 'activity_id', 'product_id'], 'fit_go_device_activity_product_unique');
        });

        $activities = ['Camping', 'Hiking', 'Running', 'Riding', 'Travelling'];

        DB::table('products')->whereNotNull('pim_payload')->orderBy('id')->chunkById(100, function ($products) use ($activities) {
            foreach ($products as $product) {
                $payload = json_decode($product->pim_payload, true);
                if (!is_array($payload)) {
                    continue;
                }

                $attributes = array_values($payload['customAtributes'] ?? $payload['customAttributes'] ?? []);
                $activityIndex = null;
                foreach ($attributes as $index => $attribute) {
                    if (strcasecmp((string) ($attribute['attributeCode'] ?? ''), 'activity') === 0) {
                        $activityIndex = $index;
                        break;
                    }
                }

                if ($activityIndex !== null && trim((string) ($attributes[$activityIndex]['value'] ?? '')) !== '') {
                    continue;
                }

                $key = (string) ($payload['generic'] ?? $product->sku ?? $product->id);
                $value = $activities[(int) ((float) sprintf('%u', crc32($key)) % count($activities))];
                if ($activityIndex === null) {
                    $attributes[] = ['attributeCode' => 'activity', 'value' => $value];
                } else {
                    $attributes[$activityIndex] = ['attributeCode' => 'activity', 'value' => $value];
                }

                $payload['customAtributes'] = $attributes;
                unset($payload['customAttributes']);
                DB::table('products')->where('id', $product->id)->update([
                    'pim_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fit_and_go_device_activity_products');
    }
};
