<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class PimCareProductMapper
{
    public function __construct(private readonly CareProductLookup $care)
    {
    }

    /**
     * Merge PIM master/enrichment data with CARE commercial data for one article.
     *
     * @return array<string, mixed>
     */
    public function map(array $product, array $imagePayload = [], ?array $scraped = null): array
    {
        $sku = (string) ($product['generic'] ?? $scraped['product_code'] ?? $scraped['sku'] ?? '');
        $name = (string) ($product['name'] ?? $scraped['product_name'] ?? '');
        $attributes = $this->attributes($product['customAtributes'] ?? $product['customAttributes'] ?? []);
        $description = (string) (
            $attributes['long_description']
            ?? $attributes['short_description']
            ?? $scraped['description']
            ?? ''
        );
        $category = (string) ($attributes['category'] ?? $product['category'] ?? $scraped['category'] ?? '');
        $gender = (string) ($attributes['gender'] ?? $product['gender'] ?? $scraped['gender'] ?? '');
        $material = $this->firstAttribute($attributes, [
            'material', 'materials', 'fabric', 'bahan', 'upper_material', 'material_description',
        ]);
        if ($material === '') {
            $material = ProductEnrichmentService::extractMaterial($description, $name, $category);
        }

        $images = $this->collectImages($product, $imagePayload, $scraped);
        $cover = (string) ($product['mainImage'] ?? ($images[0] ?? ''));
        $pimVariants = $product['variant'] ?? [];
        $care = ['found' => false, 'price' => 0, 'stock' => 0, 'variants' => []];

        try {
            if ($sku !== '') {
                $care = $this->care->get($sku);
            }
        } catch (\Throwable $e) {
            Log::warning('CARE article enrichment failed; preserving PIM catalog data', [
                'sku' => $sku,
                'error' => $e->getMessage(),
            ]);
        }

        $variants = $care['variants'] !== []
            ? $this->mergeCareVariants($care['variants'], $pimVariants, $imagePayload, $cover)
            : $this->normalizePimVariants($sku, $pimVariants, $imagePayload, $cover);

        return [
            'sku' => $sku,
            'name' => $name,
            'category' => $category,
            'gender' => $gender,
            'description' => $description,
            'material' => $material,
            'image' => $cover,
            'images' => $images,
            'price' => (float) ($care['price'] ?? 0),
            'stock' => (int) ($care['stock'] ?? 0),
            'zone_id' => ProductEnrichmentService::resolveZoneId($name, $category, $gender),
            'variants' => $variants,
            'care_found' => (bool) ($care['found'] ?? false),
            'pim_payload' => $product,
            'pim_image_payload' => $imagePayload,
        ];
    }

    private function attributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $attribute) {
            $key = strtolower(trim((string) ($attribute['attributeCode'] ?? '')));
            $value = $attribute['value'] ?? null;
            if ($key !== '' && (is_scalar($value) || $value === null)) {
                $result[$key] = trim(strip_tags((string) $value));
            }
        }
        return $result;
    }

    private function firstAttribute(array $attributes, array $keys): string
    {
        foreach ($keys as $key) {
            if (! empty($attributes[$key])) {
                return (string) $attributes[$key];
            }
        }
        return '';
    }

    private function collectImages(array $product, array $imagePayload, ?array $scraped): array
    {
        $images = [(string) ($product['mainImage'] ?? '')];
        foreach ($product['media'] ?? [] as $group) {
            foreach ($group['files'] ?? [] as $file) {
                $images[] = (string) ($file['value'] ?? '');
            }
        }
        foreach (['generic', 'variant'] as $type) {
            foreach ($imagePayload[$type] ?? [] as $entry) {
                foreach ($entry['image'] ?? [] as $item) {
                    $images[] = (string) ($item['url'] ?? '');
                }
            }
        }
        foreach ($scraped['images'] ?? [] as $item) {
            $images[] = (string) ($item['url'] ?? '');
        }
        return array_values(array_unique(array_filter($images)));
    }

    private function mergeCareVariants(array $careVariants, array $pimVariants, array $imagePayload, string $cover): array
    {
        $bySku = [];
        foreach ($pimVariants as $variant) {
            $bySku[(string) ($variant['sku'] ?? '')] = $variant;
        }
        $imageBySku = $this->variantImages($imagePayload);

        return array_map(function (array $care) use ($pimVariants, $bySku, $imageBySku, $cover) {
            $pim = $bySku[$care['sku']] ?? $this->findPimVariant($pimVariants, $care['color'], $care['size']);
            return array_merge($care, [
                'name' => $care['name'] ?: ($pim['name'] ?? ('Variant '.$care['sku'])),
                'color' => $care['color'] ?: ($pim['color'] ?? ''),
                'size' => $care['size'] ?: ($pim['size'] ?? ''),
                'ecmsku' => $pim['ecmsku'] ?? null,
                'moq' => $pim['moq'] ?? null,
                'customAttributes' => $pim['customAttributes'] ?? [],
                'image' => $imageBySku[$care['sku']] ?? ($pim ? ($imageBySku[(string) ($pim['sku'] ?? '')] ?? null) : null) ?? $cover,
            ]);
        }, $careVariants);
    }

    private function normalizePimVariants(string $articleSku, array $variants, array $imagePayload, string $cover): array
    {
        $imageBySku = $this->variantImages($imagePayload);
        $result = [];
        foreach ($variants as $variant) {
            $sku = (string) ($variant['sku'] ?? '');
            // A generic 9-digit PIM row is article metadata, not a sellable variant.
            if ($sku === '' || $sku === $articleSku) {
                continue;
            }
            $result[] = [
                'sku' => $sku,
                'name' => (string) ($variant['name'] ?? ''),
                'color' => (string) ($variant['color'] ?? ''),
                'size' => (string) ($variant['size'] ?? ''),
                'price' => 0,
                'stock' => 0,
                'ecmsku' => $variant['ecmsku'] ?? null,
                'moq' => $variant['moq'] ?? null,
                'customAttributes' => $variant['customAttributes'] ?? [],
                'image' => $imageBySku[$sku] ?? $cover,
            ];
        }
        return $result;
    }

    private function findPimVariant(array $variants, string $color, string $size): ?array
    {
        foreach ($variants as $variant) {
            $variantColor = strtolower(trim((string) ($variant['color'] ?? '')));
            $sizes = preg_split('/\s*,\s*/', strtolower((string) ($variant['size'] ?? '')), -1, PREG_SPLIT_NO_EMPTY);
            if (($variantColor === '' || $variantColor === strtolower($color))
                && ($sizes === [] || in_array(strtolower($size), $sizes, true))) {
                return $variant;
            }
        }
        return null;
    }

    private function variantImages(array $imagePayload): array
    {
        $images = [];
        foreach ($imagePayload['variant'] ?? [] as $entry) {
            $sku = (string) ($entry['sku'] ?? '');
            $url = (string) ($entry['image'][0]['url'] ?? '');
            if ($sku !== '' && $url !== '') {
                $images[$sku] = $url;
            }
        }
        return $images;
    }
}
