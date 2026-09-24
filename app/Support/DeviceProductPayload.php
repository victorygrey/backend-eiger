<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

class DeviceProductPayload
{
    /** @return list<string> */
    public static function relations(): array
    {
        return [
            'zone',
            'atomCategory',
            'atomSubCategory',
            'variants.attributesRelation',
            'technologiesRelation',
            'activitiesRelation.atomActivity.group',
            'performancesRelation',
            'specificationsRelation',
            'customAttributesRelation',
            'mediaRelation',
            'pimRecord',
        ];
    }

    /**
     * Build the shared product contract consumed by all in-store devices.
     * Prices and stocks are the latest CARE values stored by the CMS, while
     * descriptive fields and media originate from PIM.
     *
     * @return array<string, mixed>
     */
    public static function make(Product $product): array
    {
        $product->loadMissing(self::relations());

        $media = collect($product->pim_media ?? [])
            ->map(function (mixed $item): ?array {
                if (is_string($item)) {
                    return ['type' => self::mediaType(null, $item), 'role' => 'image', 'url' => PimMediaUrl::toPublicUrl($item)];
                }

                if (! is_array($item)) {
                    return null;
                }

                $url = $item['url'] ?? $item['value'] ?? null;
                if (! is_string($url) || $url === '') {
                    return null;
                }

                return array_filter([
                    'id' => $item['id'] ?? null,
                    'type' => self::mediaType($item['mime'] ?? $item['type'] ?? null, $url),
                    'role' => $item['role'] ?? $item['attributeCode'] ?? 'image',
                    'url' => PimMediaUrl::toPublicUrl($url),
                    'description' => $item['description'] ?? null,
                    'sku' => $item['sku'] ?? null,
                ], fn (mixed $value): bool => $value !== null && $value !== '');
            })
            ->filter()
            ->unique('url')
            ->values();

        $primaryImage = PimMediaUrl::toPublicUrl($product->image);
        if ($primaryImage) {
            // The cover has one canonical location in `image`; `media` only
            // contains supplemental assets so clients never receive the URL twice.
            $media = $media->reject(fn (array $item): bool => ($item['url'] ?? null) === $primaryImage)->values();
        }

        $variants = $product->variants->map(fn ($variant): array => [
            'id' => $variant->id,
            'sku' => $variant->sku,
            'name' => $variant->name,
            'color' => $variant->color,
            'size' => $variant->size,
            'price' => (float) $variant->price,
            'stock' => (int) $variant->stock,
            // CARE may create a sellable size/color variant before PIM sends
            // variant-specific media. Devices still need a usable image, so
            // inherit the locally stored product cover until that media arrives.
            'image' => PimMediaUrl::toPublicUrl($variant->image) ?? $primaryImage,
            'ecmsku' => $variant->ecmsku,
            'moq' => $variant->moq,
            'custom_attributes' => $variant->custom_attributes,
        ])->values();

        $technologies = $product->technologies;
        $activities = $product->activities;
        $performances = $product->performances;
        $specifications = $product->specifications;
        $customAttributes = $product->custom_attributes_list;

        return [
            'id' => $product->id,
            'slug' => Str::slug($product->name).'-'.$product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'description' => $product->description,
            'category' => $product->category,
            'product_group' => $product->product_group,
            'gender' => $product->gender,
            'material' => $product->material,
            'weight' => $product->weight,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
            'currency' => 'IDR',
            'zone' => $product->zone ? [
                'id' => $product->zone->id,
                'code' => $product->zone->code,
                'name' => $product->zone->name,
            ] : null,
            'atom_category' => $product->atomCategory ? [
                'id' => $product->atomCategory->id,
                'external_id' => $product->atomCategory->external_id,
                'slug' => $product->atomCategory->slug,
                'name' => $product->atomCategory->name,
            ] : null,
            'atom_sub_category' => $product->atomSubCategory ? [
                'id' => $product->atomSubCategory->id,
                'external_id' => $product->atomSubCategory->external_id,
                'slug' => $product->atomSubCategory->slug,
                'name' => $product->atomSubCategory->name,
            ] : null,
            'image' => $primaryImage,
            'media' => $media->all(),
            'variants' => $variants->all(),
            'technologies' => $technologies,
            'activities' => $activities,
            'performances' => $performances,
            'specifications' => $specifications,
            'custom_attributes' => $customAttributes,
            'data_sources' => [
                'product' => 'PIM',
                'commercial' => 'CARE',
                'pim_synced_at' => $product->pim_synced_at?->toIso8601String(),
                'care_synced_at' => $product->care_synced_at?->toIso8601String(),
            ],
        ];
    }

    private static function mediaType(mixed $hint, string $url): string
    {
        $hint = strtolower((string) $hint);

        return str_contains($hint, 'video') || preg_match('/\.(mp4|webm|mov)(?:\?|$)/i', $url)
            ? 'video'
            : 'image';
    }
}
