<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->index();
            $table->string('gender', 50)->nullable()->index();
            $table->string('product_group')->nullable()->index();
            $table->decimal('weight', 12, 3)->nullable();
            $table->timestamp('pim_synced_at')->nullable()->index();
            $table->timestamp('care_synced_at')->nullable()->index();
        });

        Schema::create('product_pim_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('generic_sku')->index();
            $table->string('source', 50)->default('pim');
            $table->string('schema_version')->nullable();
            $table->string('payload_checksum', 64)->index();
            $table->json('product_payload');
            $table->json('image_payload')->nullable();
            $table->timestamp('received_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('product_custom_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('attribute_code')->index();
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'attribute_code']);
        });

        Schema::create('product_technologies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('pim_id')->nullable()->index();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->text('image_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('pim_id')->nullable()->index();
            $table->string('name')->index();
            $table->text('description')->nullable();
            $table->boolean('is_selected')->default(false)->index();
            $table->decimal('rating', 5, 2)->nullable();
            $table->string('rating_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_specifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('code')->index();
            $table->string('name')->nullable();
            $table->text('value')->nullable();
            $table->string('unit', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_id', 'code']);
        });

        Schema::create('product_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->string('sku')->nullable()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('attribute_code')->nullable()->index();
            $table->string('role', 50)->default('gallery')->index();
            $table->string('media_type', 50)->nullable();
            $table->text('url');
            $table->text('source_url')->nullable();
            $table->string('source', 50)->nullable();
            $table->text('description')->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'product_variant_id', 'role'], 'product_media_owner_role_index');
        });

        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('attribute_code')->index();
            $table->text('value')->nullable();
            $table->string('value_type', 30)->default('text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['product_variant_id', 'attribute_code']);
        });

        $now = now();
        DB::table('products')->orderBy('id')->chunkById(100, function ($products) use ($now) {
            foreach ($products as $product) {
                $payload = $this->decode($product->pim_payload ?? null);
                $imagePayload = $this->decode($product->pim_image_payload ?? null);
                $media = $this->decode($product->pim_media ?? null) ?? [];
                if (is_array($payload)) {
                    $attributes = $payload['customAtributes'] ?? $payload['customAttributes'] ?? [];
                    $attributeMap = [];
                    foreach ($attributes as $position => $attribute) {
                        $code = trim((string) ($attribute['attributeCode'] ?? ''));
                        if ($code === '') continue;
                        $value = $attribute['value'] ?? null;
                        $attributeMap[strtolower($code)] = is_scalar($value) || $value === null ? $value : json_encode($value);
                        DB::table('product_custom_attributes')->updateOrInsert(
                            ['product_id' => $product->id, 'attribute_code' => $code],
                            ['value' => is_scalar($value) || $value === null ? $value : json_encode($value),
                                'value_type' => $this->valueType($value), 'sort_order' => $position,
                                'created_at' => $now, 'updated_at' => $now]
                        );
                    }

                    DB::table('products')->where('id', $product->id)->update([
                        'category' => $attributeMap['category'] ?? $payload['category'] ?? null,
                        'gender' => $attributeMap['gender'] ?? $payload['gender'] ?? null,
                        'product_group' => $attributeMap['product_group'] ?? null,
                        'weight' => is_numeric($payload['weight'] ?? null) ? $payload['weight'] : null,
                        'pim_synced_at' => $product->updated_at ?? $now,
                    ]);

                    DB::table('product_pim_records')->insert([
                        'product_id' => $product->id,
                        'generic_sku' => (string) ($payload['generic'] ?? $product->sku),
                        'source' => 'pim',
                        'schema_version' => $product->pim_version ?? null,
                        'payload_checksum' => hash('sha256', json_encode([$payload, $imagePayload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                        'product_payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'image_payload' => $imagePayload === null ? null : json_encode($imagePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        'received_at' => $product->updated_at ?? $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $this->insertTechnology($product->id, $payload['technology'] ?? [], $now);
                    $this->insertActivities($product->id, $payload['activity'] ?? [], $now);
                    $this->insertSpecifications($product->id, $payload['specification'] ?? [], $now);
                }

                $this->insertMedia($product->id, is_array($media) ? $media : [], $now);
            }
        });

        DB::table('product_variants')->whereNotNull('custom_attributes')->orderBy('id')->chunkById(100, function ($variants) use ($now) {
            foreach ($variants as $variant) {
                foreach ($this->decode($variant->custom_attributes) ?? [] as $position => $attribute) {
                    $code = trim((string) ($attribute['attributeCode'] ?? ''));
                    if ($code === '') continue;
                    $value = $attribute['value'] ?? null;
                    DB::table('product_variant_attributes')->updateOrInsert(
                        ['product_variant_id' => $variant->id, 'attribute_code' => $code],
                        ['value' => is_scalar($value) || $value === null ? $value : json_encode($value),
                            'value_type' => $this->valueType($value), 'sort_order' => $position,
                            'created_at' => $now, 'updated_at' => $now]
                    );
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['pim_media', 'pim_version', 'pim_payload', 'pim_image_payload']);
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('custom_attributes');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('pim_media')->nullable();
            $table->string('pim_version')->nullable();
            $table->json('pim_payload')->nullable();
            $table->json('pim_image_payload')->nullable();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('custom_attributes')->nullable();
        });

        DB::table('product_pim_records')->orderBy('id')->each(function ($record) {
            DB::table('products')->where('id', $record->product_id)->update([
                'pim_version' => $record->schema_version,
                'pim_payload' => $record->product_payload,
                'pim_image_payload' => $record->image_payload,
            ]);
        });
        DB::table('products')->orderBy('id')->each(function ($product) {
            $media = DB::table('product_media')->where('product_id', $product->id)->orderBy('sort_order')->get()
                ->map(fn ($item) => array_filter([
                    'url' => $item->url, 'role' => $item->role, 'source' => $item->source,
                    'source_url' => $item->source_url, 'description' => $item->description,
                    'sku' => $item->sku, 'sha256' => $item->checksum, 'mime' => $item->mime_type,
                ], fn ($value) => $value !== null))->values()->all();
            DB::table('products')->where('id', $product->id)->update(['pim_media' => json_encode($media)]);
        });
        DB::table('product_variants')->orderBy('id')->each(function ($variant) {
            $attributes = DB::table('product_variant_attributes')->where('product_variant_id', $variant->id)
                ->orderBy('sort_order')->get()->map(fn ($item) => [
                    'attributeCode' => $item->attribute_code, 'value' => $item->value,
                ])->values()->all();
            DB::table('product_variants')->where('id', $variant->id)->update(['custom_attributes' => json_encode($attributes)]);
        });

        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_media');
        Schema::dropIfExists('product_specifications');
        Schema::dropIfExists('product_activities');
        Schema::dropIfExists('product_technologies');
        Schema::dropIfExists('product_custom_attributes');
        Schema::dropIfExists('product_pim_records');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['category', 'gender', 'product_group', 'weight', 'pim_synced_at', 'care_synced_at']);
        });
    }

    private function decode(mixed $value): ?array
    {
        if (is_array($value)) return $value;
        if (! is_string($value) || $value === '') return null;
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean', is_int($value) => 'integer', is_float($value) => 'decimal',
            is_array($value) => 'json', $value === null => 'null', default => 'text',
        };
    }

    private function insertTechnology(int $productId, array $rows, $now): void
    {
        foreach ($rows as $position => $row) DB::table('product_technologies')->insert([
            'product_id' => $productId, 'pim_id' => $row['id'] ?? null, 'name' => $row['name'] ?? 'Technology',
            'description' => $row['description'] ?? null, 'image_url' => $row['image'] ?? null,
            'sort_order' => $position, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertActivities(int $productId, array $rows, $now): void
    {
        foreach ($rows as $position => $row) DB::table('product_activities')->insert([
            'product_id' => $productId, 'pim_id' => $row['id'] ?? null, 'name' => $row['name'] ?? 'Activity',
            'description' => $row['description'] ?? null, 'is_selected' => (bool) ($row['selected'] ?? false),
            'rating' => is_numeric($row['rating'] ?? null) ? $row['rating'] : null,
            'rating_description' => $row['desc_rating'] ?? null, 'sort_order' => $position,
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function insertSpecifications(int $productId, array $rows, $now): void
    {
        foreach ($rows as $position => $row) {
            $code = trim((string) ($row['code'] ?? $row['name'] ?? ''));
            if ($code === '') continue;
            DB::table('product_specifications')->updateOrInsert(
                ['product_id' => $productId, 'code' => $code],
                ['name' => $row['name'] ?? null, 'value' => $row['value'] ?? null, 'unit' => $row['unit'] ?? null,
                    'sort_order' => $position, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    private function insertMedia(int $productId, array $rows, $now): void
    {
        foreach ($rows as $position => $row) {
            if (is_string($row)) $row = ['url' => $row];
            $url = $row['url'] ?? $row['value'] ?? null;
            if (! is_string($url) || $url === '') continue;
            $variantId = ! empty($row['sku'])
                ? DB::table('product_variants')->where('product_id', $productId)->where('sku', $row['sku'])->value('id')
                : null;
            DB::table('product_media')->insert([
                'product_id' => $productId, 'product_variant_id' => $variantId, 'sku' => $row['sku'] ?? null,
                'external_id' => $row['id'] ?? null, 'attribute_code' => $row['attributeCode'] ?? null,
                'role' => $row['role'] ?? $row['type'] ?? 'gallery', 'media_type' => $row['type'] ?? null,
                'url' => $url, 'source_url' => $row['source_url'] ?? null, 'source' => $row['source'] ?? null,
                'description' => $row['description'] ?? null, 'checksum' => $row['sha256'] ?? null,
                'mime_type' => $row['mime'] ?? null, 'sort_order' => $position,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }
};
