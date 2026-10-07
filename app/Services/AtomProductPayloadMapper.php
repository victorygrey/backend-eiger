<?php

namespace App\Services;

use Illuminate\Support\Str;

class AtomProductPayloadMapper
{
    /**
     * Convert the public ATOM app response to the existing official PIM contract.
     * Device payload builders keep reading the same normalized product tables.
     *
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $detail
     * @param  array<int, array<string, mixed>>  $variants
     * @param  array<int, array<string, mixed>>  $variantImages
     * @return array{product: array<string, mixed>, image: array<string, mixed>}
     */
    public function map(array $selection, array $detail, array $variants, array $variantImages): array
    {
        $generic = (string) ($detail['skuProduct'] ?? $selection['sku']);
        $name = (string) ($detail['name'] ?? $selection['name']);
        $imageGroups = $this->imageGroups($variantImages);
        $variantRows = $this->variants($name, $variants, $imageGroups);
        $allImages = $this->allImages($detail, $variantImages);
        $mainImage = $allImages[0]['url'] ?? null;
        $activity = is_array($detail['activity'] ?? null) ? $detail['activity'] : [];

        $product = array_filter([
            'generic' => $generic,
            'name' => $name,
            'mainImage' => $mainImage,
            'weight' => is_numeric($detail['weight'] ?? null) ? (float) $detail['weight'] : null,
            'customAtributes' => $this->attributes($selection, $detail),
            'variant' => $variantRows,
            'technology' => $this->technology($detail['technology'] ?? []),
            'activity' => $activity === [] ? [] : [[
                'id' => $activity['id'] ?? null,
                'name' => $activity['name'] ?? $selection['activity'],
                'description' => $activity['description'] ?? null,
                'selected' => true,
            ]],
            'performance' => $this->ratings($detail['performanceRating'] ?? []),
            'specification' => $this->specifications($detail['specifications'] ?? []),
        ], fn ($value, $key) => $key !== 'weight' || $value !== null, ARRAY_FILTER_USE_BOTH);

        $genericImages = [[
            'sku' => $generic,
            'image' => array_map(function ($item, $index) {
                return [
                    'id' => $item['id'] ?? null,
                    'type' => $index === 0 ? 'main_image' : 'gallery',
                    'url' => $item['url'],
                    'source' => 'ATOM',
                ];
            }, $allImages, array_keys($allImages)),
        ]];

        $variantPayload = [];
        foreach ($variantRows as $index => $variant) {
            $url = $variant['_image_url'] ?? $mainImage;
            if ($url) {
                $variantPayload[] = [
                    'sku' => $variant['sku'],
                    'image' => [[
                        'type' => 'main_image',
                        'url' => $url,
                        'source' => 'ATOM',
                    ]],
                ];
            }
            unset($product['variant'][$index]['_image_url']);
        }

        return [
            'product' => $product,
            'image' => ['generic' => $genericImages, 'variant' => $variantPayload],
        ];
    }

    /** @return array<int, array{attributeCode: string, value: string}> */
    private function attributes(array $selection, array $detail): array
    {
        $category = is_array($detail['category'] ?? null) ? $detail['category'] : [];
        $subCategory = is_array($detail['subCategory'] ?? null) ? $detail['subCategory'] : [];
        $type = is_array($detail['type'] ?? null) ? $detail['type'] : [];
        $activity = is_array($detail['activity'] ?? null) ? $detail['activity'] : [];
        $values = [
            'short_description' => $detail['shortDescription'] ?? '',
            'long_description' => $detail['description'] ?? '',
            'material' => $detail['material'] ?? '',
            'category' => $category['name'] ?? '',
            'category_code' => $category['slug'] ?? '',
            'sub_category' => $subCategory['name'] ?? '',
            'sub_category_code' => $subCategory['slug'] ?? '',
            'product_group' => $type['name'] ?? $selection['product_type'],
            'gender' => Str::upper((string) $selection['gender']),
            'activity' => $activity['name'] ?? $selection['activity'],
            'atom_slug' => $detail['slug'] ?? $selection['slug'],
            'atom_source' => 'ATOM App API',
            'atom_gender_source' => $selection['gender_source'],
            'atom_requested_product_type' => $selection['product_type'],
        ];

        return collect($values)->map(fn ($value, $code) => [
            'attributeCode' => $code,
            'value' => is_scalar($value) ? (string) $value : '',
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function variants(string $productName, array $variants, array $imageGroups): array
    {
        $variantCount = count($variants);
        $groupCount = count($imageGroups);

        return collect($variants)->values()->map(function ($row, $index) use (
            $productName, $imageGroups, $variantCount, $groupCount
        ) {
            $groupIndex = match (true) {
                $groupCount <= 1 => 0,
                $variantCount === $groupCount => $index,
                $variantCount % $groupCount === 0 => intdiv($index, max(1, intdiv($variantCount, $groupCount))),
                default => $index % $groupCount,
            };
            $group = $imageGroups[$groupIndex] ?? $imageGroups[0] ?? ['color' => '', 'images' => []];
            $price = $row['price'] ?? [];
            $size = (string) ($row['size'] ?? '');
            $color = (string) ($group['color'] ?? '');

            return [
                'sku' => (string) ($row['sku'] ?? ''),
                'name' => trim($productName.' - '.$color.' - '.$size, ' -'),
                'color' => $color,
                'size' => $size,
                'ecmsku' => (string) ($row['sku'] ?? ''),
                'moq' => null,
                'price' => (float) ($price['sellingPriceNumber'] ?? $price['originalPriceNumber'] ?? 0),
                'stock' => (int) ($row['stock'] ?? 0),
                'customAttributes' => [[
                    'attributeCode' => 'atom_variant_id',
                    'value' => (string) ($row['atom_id'] ?? ''),
                ]],
                '_image_url' => data_get($group, 'images.0.url'),
            ];
        })->filter(fn ($row) => $row['sku'] !== '')->values()->all();
    }

    /** @return array<int, array{color: string, images: array<int, array<string, mixed>>}> */
    private function imageGroups(array $variantImages): array
    {
        return collect($variantImages)->groupBy(fn ($row) => (string) data_get($row, 'color.name', $row['title'] ?? ''))
            ->map(fn ($rows, $color) => [
                'color' => $color,
                'images' => $rows->map(fn ($row) => [
                    'id' => $row['atom_id'] ?? null,
                    'url' => $this->normalizeUrl($row['image'] ?? null),
                ])->filter(fn ($row) => is_string($row['url']) && $row['url'] !== '')->unique('url')->values()->all(),
            ])->values()->all();
    }

    /** @return array<int, array{id: mixed, url: string}> */
    private function allImages(array $detail, array $variantImages): array
    {
        $images = [];
        foreach ($detail['files'] ?? [] as $row) {
            if (($row['type'] ?? 'image') === 'image' && is_string($row['url'] ?? null)) {
                $images[] = ['id' => $row['id'] ?? null, 'url' => $this->normalizeUrl($row['url'])];
            }
        }
        foreach ($variantImages as $row) {
            if (is_string($row['image'] ?? null)) {
                $images[] = ['id' => $row['atom_id'] ?? null, 'url' => $this->normalizeUrl($row['image'])];
            }
        }

        return collect($images)->filter(fn ($row) => filled($row['url']))->unique('url')->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function technology(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)->map(fn ($row) => [
            'id' => $row['id'] ?? null,
            'name' => $row['name'] ?? $row['title'] ?? 'Technology',
            'description' => $row['description'] ?? null,
            'image' => $this->normalizeUrl($row['image'] ?? $row['iconUrl'] ?? null),
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function ratings(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)->map(fn ($row) => [
            'id' => $row['id'] ?? null,
            'name' => $row['name'] ?? $row['title'] ?? 'Performance',
            'description' => $row['description'] ?? null,
            'selected' => $row['selected'] ?? false,
            'rating' => $row['rating'] ?? null,
            'desc_rating' => $row['desc_rating'] ?? $row['ratingDescription'] ?? null,
        ])->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function specifications(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        return collect($rows)->map(fn ($row) => [
            'code' => $row['code'] ?? $row['name'] ?? null,
            'name' => $row['name'] ?? null,
            'value' => is_scalar($row['value'] ?? null) ? (string) $row['value'] : null,
            'unit' => $row['unit'] ?? null,
        ])->filter(fn ($row) => filled($row['code']))->values()->all();
    }

    private function normalizeUrl(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $parts = parse_url(trim($value));
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $path = implode('/', array_map(
            fn (string $segment) => rawurlencode(rawurldecode($segment)),
            explode('/', (string) ($parts['path'] ?? '')),
        ));
        $url = strtolower($parts['scheme']).'://'.strtolower($parts['host']);
        if (isset($parts['port'])) {
            $url .= ':'.$parts['port'];
        }
        $url .= $path;
        if (isset($parts['query'])) {
            $url .= '?'.$parts['query'];
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
