<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PimPayload
{
    private const DUMMY_ACTIVITIES = ['Camping', 'Hiking', 'Running', 'Riding', 'Travelling'];

    public function validate(array $input): array
    {
        $rules = [
            'product' => 'required|array',
            'product.generic' => 'required|string|max:100',
            'product.name' => 'required|string|max:255',
            'product.mainImage' => 'nullable|url|max:8192',
            'product.weight' => 'sometimes|numeric|min:0',
            'product.variant' => 'required|array|min:1',
            'product.variant.*.sku' => 'required|string|max:100|distinct',
            'product.variant.*.name' => 'required|string|max:255',
            'product.variant.*.color' => 'sometimes|nullable|string',
            'product.variant.*.size' => 'sometimes|nullable|string',
            'product.variant.*.moq' => 'sometimes|nullable|string',
            'product.variant.*.ecmsku' => 'sometimes|nullable|string',
            'product.variant.*.customAttributes' => 'sometimes|array',
            'product.media' => 'sometimes|array',
            'product.media.*.attributeCode' => 'required|string',
            'product.media.*.files' => 'present|array',
            'product.media.*.files.*.value' => 'required|url|max:8192',
            'product.technology' => 'sometimes|array',
            'product.activity' => 'sometimes|array',
            'product.specification' => 'sometimes|array',
            'image' => 'present|array',
        ];
        foreach (['customAttributes', 'customAtributes'] as $key) {
            $rules["product.$key"] = 'sometimes|array';
            $rules["product.$key.*.attributeCode"] = 'required|string';
            $rules["product.$key.*.value"] = 'present|nullable|string';
        }
        foreach (['generic', 'variant'] as $key) {
            $rules["image.$key"] = 'sometimes|array';
            $rules["image.$key.*.sku"] = 'required|string|max:100|distinct';
            $rules["image.$key.*.image"] = 'present|array';
            $rules["image.$key.*.image.*.url"] = 'required|url|max:8192';
            $rules["image.$key.*.image.*.type"] = 'required|string';
        }
        Validator::make($input, $rules)->validate();
        $product = $input['product'];
        // The documented spelling is intentional; accept older simulator payloads too.
        $product['customAtributes'] = collect(array_merge($product['customAttributes'] ?? [], $product['customAtributes'] ?? []))
            ->keyBy('attributeCode')->values()->all();
        unset($product['customAttributes']);
        $product = $this->withDummyActivity($product);
        $image = $input['image'] + ['generic' => [], 'variant' => []];
        foreach ($image['generic'] as $row) {
            if ($row['sku'] !== $product['generic']) {
                throw ValidationException::withMessages(['image.generic' => 'Gambar generic tidak sesuai artikel PIM.']);
            }
        }
        foreach ($image['variant'] as $row) {
            if (! in_array($row['sku'], array_column($product['variant'], 'sku'), true)) {
                throw ValidationException::withMessages(['image.variant' => 'SKU gambar tidak terdapat dalam payload produk PIM.']);
            }
        }

        return ['product' => $product, 'image' => $image];
    }

    /**
     * Supply stable demo activity master data without replacing a real PIM value.
     */
    public function withDummyActivity(array $product): array
    {
        $attributes = array_values($product['customAtributes'] ?? []);
        $activityIndex = null;

        foreach ($attributes as $index => $attribute) {
            if (strcasecmp((string) ($attribute['attributeCode'] ?? ''), 'activity') === 0) {
                $activityIndex = $index;
                break;
            }
        }

        if ($activityIndex === null) {
            $attributes[] = [
                'attributeCode' => 'activity',
                'value' => self::dummyActivity((string) ($product['generic'] ?? $product['name'] ?? '')),
            ];
        } elseif (trim((string) ($attributes[$activityIndex]['value'] ?? '')) === '') {
            $attributes[$activityIndex]['attributeCode'] = 'activity';
            $attributes[$activityIndex]['value'] = self::dummyActivity((string) ($product['generic'] ?? $product['name'] ?? ''));
        }

        $product['customAtributes'] = $attributes;

        return $product;
    }

    public static function dummyActivity(string $key): string
    {
        $index = (int) ((float) sprintf('%u', crc32($key)) % count(self::DUMMY_ACTIVITIES));

        return self::DUMMY_ACTIVITIES[$index];
    }

    public function assets(array $product, array $image, string $sku): array
    {
        $assets = collect($image['variant'] ?? [])->firstWhere('sku', $sku)['image'] ?? [];
        if (empty($assets)) {
            $generic = collect($image['generic'] ?? [])->firstWhere('sku', $product['generic'])['image'] ?? [];
            if (! empty($generic)) {
                $assets = $generic;
            }
        }
        if (! collect($assets)->contains('type', 'main_image')) {
            $generic = collect($image['generic'] ?? [])->firstWhere('sku', $product['generic'])['image'] ?? [];
            $main = collect($generic)->firstWhere('type', 'main_image');
            if (! $main && ! empty($product['mainImage'])) {
                $main = ['type' => 'main_image', 'url' => $product['mainImage']];
            }
            if ($main) {
                array_unshift($assets, $main);
            }
        }

        return collect($assets)->unique('url')->values()->all();
    }

    public function mediaValues(array $product, array $image, string $sku, bool $includeSupplemental = true): array
    {
        $assets = $this->assets($product, $image, $sku);
        $context = [
            'product_name' => $product['name'] ?? $sku,
            'generic_sku' => $product['generic'] ?? $sku,
        ];
        $media = array_map(function ($asset) use ($context) {
            $role = $asset['type'] === 'main_image' ? 'main_image' : 'gallery';

            $stored = $this->storeOrLink($asset['url'], $role, $context);

            return array_merge($asset, $stored, [
                'source_url' => $asset['url'],
                'role' => $role,
            ]);
        }, $assets);

        return [
            'pim_media' => array_merge($media, $includeSupplemental ? $this->supplementalMedia($product, true) : []),
            'image' => collect($media)->firstWhere('role', 'main_image')['url'] ?? ($media[0]['url'] ?? null),
        ];
    }

    public function supplementalMedia(array $product, bool $storeLocally = false): array
    {
        $media = [];
        $context = [
            'product_name' => $product['name'] ?? $product['generic'] ?? 'product',
            'generic_sku' => $product['generic'] ?? 'unknown',
        ];
        foreach ($product['media'] ?? [] as $group) {
            foreach ($group['files'] ?? [] as $file) {
                $role = (string) $group['attributeCode'];
                $entry = ['url' => $file['value'], 'role' => $role,
                    'description' => $file['description'] ?? '', 'sku' => $product['generic'],
                    'source_url' => $file['value'], 'attributeCode' => $role];
                if ($storeLocally && config('pim.copy_http_media')) {
                    $entry = array_merge($entry, $this->storeOrLink($file['value'], $role, $context));
                }
                $media[] = $entry;
            }
        }
        foreach ($product['technology'] ?? [] as $technology) {
            if (! empty($technology['image'])) {
                $entry = ['url' => $technology['image'], 'role' => 'technology',
                    'description' => $technology['name'] ?? '', 'sku' => $product['generic'],
                    'source_url' => $technology['image']];
                if ($storeLocally && config('pim.copy_http_media')) {
                    $entry = array_merge($entry, $this->storeOrLink($technology['image'], 'technology', $context));
                }
                $media[] = $entry;
            }
        }

        return $media;
    }

    private function storeOrLink(string $url, string $role, array $context): array
    {
        if (! config('pim.copy_http_media')) {
            return ['url' => $url, 'role' => $role];
        }

        try {
            return app(PimHttpMediaImporter::class)->import($url, $role, $context);
        } catch (ConnectionException|\RuntimeException) {
            $path = strtolower((string) parse_url($url, PHP_URL_PATH));
            $isVideo = str_contains(strtolower($role), 'video')
                || str_ends_with($path, '.mp4')
                || str_ends_with($path, '.webm');

            return [
                'url' => $url,
                'role' => $role,
                'type' => $isVideo ? 'video' : 'image',
                'source' => 'pim_remote_fallback',
            ];
        }
    }
}
