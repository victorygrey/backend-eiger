<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class PimProductDataStore
{
    public function replace(
        Product $product,
        array $payload,
        array $imagePayload = [],
        array $media = [],
        ?string $version = null,
        string $source = 'pim',
        array $variantMedia = [],
    ): void {
        DB::transaction(function () use ($product, $payload, $imagePayload, $media, $version, $source, $variantMedia) {
            $attributes = $this->attributes($payload['customAtributes'] ?? $payload['customAttributes'] ?? []);
            $product->updateQuietly([
                'category' => $attributes['category'] ?? $payload['category'] ?? null,
                'gender' => $attributes['gender'] ?? $payload['gender'] ?? null,
                'product_group' => $attributes['product_group'] ?? null,
                'weight' => is_numeric($payload['weight'] ?? null) ? $payload['weight'] : null,
                'pim_synced_at' => now(),
            ]);

            $product->pimRecord()->updateOrCreate([], [
                'generic_sku' => (string) ($payload['generic'] ?? $product->sku),
                'source' => $source,
                'schema_version' => $version,
                'payload_checksum' => hash('sha256', json_encode([$payload, $imagePayload], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                'product_payload' => $payload,
                'image_payload' => $imagePayload,
                'received_at' => now(),
            ]);

            $product->customAttributesRelation()->delete();
            foreach (array_values($payload['customAtributes'] ?? $payload['customAttributes'] ?? []) as $position => $row) {
                $code = trim((string) ($row['attributeCode'] ?? ''));
                if ($code === '') continue;
                $value = $row['value'] ?? null;
                $product->customAttributesRelation()->create([
                    'attribute_code' => $code,
                    'value' => $this->scalarValue($value),
                    'value_type' => $this->valueType($value),
                    'sort_order' => $position,
                ]);
            }

            $product->technologiesRelation()->delete();
            foreach (array_values($payload['technology'] ?? []) as $position => $row) {
                $product->technologiesRelation()->create([
                    'pim_id' => $row['id'] ?? null, 'name' => $row['name'] ?? 'Technology',
                    'description' => $row['description'] ?? null, 'image_url' => $row['image'] ?? null,
                    'sort_order' => $position,
                ]);
            }

            $product->activitiesRelation()->delete();
            foreach (array_values($payload['activity'] ?? []) as $position => $row) {
                $product->activitiesRelation()->create([
                    'pim_id' => $row['id'] ?? null, 'name' => $row['name'] ?? 'Activity',
                    'description' => $row['description'] ?? null, 'is_selected' => (bool) ($row['selected'] ?? false),
                    'rating' => is_numeric($row['rating'] ?? null) ? $row['rating'] : null,
                    'rating_description' => $row['desc_rating'] ?? null, 'sort_order' => $position,
                ]);
            }

            $product->specificationsRelation()->delete();
            foreach (array_values($payload['specification'] ?? []) as $position => $row) {
                $code = trim((string) ($row['code'] ?? $row['name'] ?? ''));
                if ($code === '') continue;
                $product->specificationsRelation()->create([
                    'code' => $code, 'name' => $row['name'] ?? null, 'value' => $this->scalarValue($row['value'] ?? null),
                    'unit' => $row['unit'] ?? null, 'sort_order' => $position,
                ]);
            }

            $product->mediaRelation()->delete();
            $this->storeMedia($product, $media, null);
            foreach ($variantMedia as $sku => $rows) {
                $variant = $product->variants()->where('sku', $sku)->first();
                if ($variant) $this->storeMedia($product, $rows, $variant);
            }

            $product->unsetRelation('pimRecord');
            $product->unsetRelation('customAttributesRelation');
            $product->unsetRelation('technologiesRelation');
            $product->unsetRelation('activitiesRelation');
            $product->unsetRelation('specificationsRelation');
            $product->unsetRelation('mediaRelation');
        });
    }

    public function replaceVariantAttributes(ProductVariant $variant, array $attributes): void
    {
        $variant->attributesRelation()->delete();
        foreach (array_values($attributes) as $position => $row) {
            $code = trim((string) ($row['attributeCode'] ?? ''));
            if ($code === '') continue;
            $value = $row['value'] ?? null;
            $variant->attributesRelation()->create([
                'attribute_code' => $code, 'value' => $this->scalarValue($value),
                'value_type' => $this->valueType($value), 'sort_order' => $position,
            ]);
        }
        $variant->unsetRelation('attributesRelation');
    }

    private function storeMedia(Product $product, array $rows, ?ProductVariant $variant): void
    {
        foreach (array_values($rows) as $position => $row) {
            if (is_string($row)) $row = ['url' => $row];
            $url = $row['url'] ?? $row['value'] ?? null;
            if (! is_string($url) || $url === '') continue;
            $product->mediaRelation()->create([
                'product_variant_id' => $variant?->id,
                'sku' => $variant?->sku ?? $row['sku'] ?? $product->sku,
                'external_id' => $row['id'] ?? null,
                'attribute_code' => $row['attributeCode'] ?? null,
                'role' => $row['role'] ?? $row['type'] ?? 'gallery',
                'media_type' => $row['type'] ?? null,
                'url' => $url,
                'source_url' => $row['source_url'] ?? null,
                'source' => $row['source'] ?? null,
                'description' => $row['description'] ?? null,
                'checksum' => $row['sha256'] ?? null,
                'mime_type' => $row['mime'] ?? null,
                'sort_order' => $position,
            ]);
        }
    }

    private function attributes(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $code = strtolower(trim((string) ($row['attributeCode'] ?? '')));
            if ($code !== '') $result[$code] = $this->scalarValue($row['value'] ?? null);
        }
        return $result;
    }

    private function scalarValue(mixed $value): mixed
    {
        return is_scalar($value) || $value === null ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function valueType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean', is_int($value) => 'integer', is_float($value) => 'decimal',
            is_array($value) => 'json', $value === null => 'null', default => 'text',
        };
    }
}
