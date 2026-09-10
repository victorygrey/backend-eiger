<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PimPayload
{
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
            'image' => 'required|array',
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
        $image = $input['image'] + ['generic' => [], 'variant' => []];
        foreach ($image['generic'] as $row) {
            if ($row['sku'] !== $product['generic']) {
                throw ValidationException::withMessages(['image.generic' => 'Gambar generic tidak sesuai artikel PIM.']);
            }
        }
        foreach ($image['variant'] as $row) {
            if (!in_array($row['sku'], array_column($product['variant'], 'sku'), true)) {
                throw ValidationException::withMessages(['image.variant' => 'SKU gambar tidak terdapat dalam payload produk PIM.']);
            }
        }
        return ['product' => $product, 'image' => $image];
    }

    public function assets(array $product, array $image, string $sku): array
    {
        $assets = collect($image['variant'] ?? [])->firstWhere('sku', $sku)['image'] ?? [];
        if (!collect($assets)->contains('type', 'main_image')) {
            $generic = collect($image['generic'] ?? [])->firstWhere('sku', $product['generic'])['image'] ?? [];
            $main = collect($generic)->firstWhere('type', 'main_image');
            if (!$main && !empty($product['mainImage'])) $main = ['type' => 'main_image', 'url' => $product['mainImage']];
            if ($main) array_unshift($assets, $main);
        }
        return collect($assets)->unique('url')->values()->all();
    }

    public function mediaValues(array $product, array $image, string $sku): array
    {
        $assets = $this->assets($product, $image, $sku);
        $media = array_map(function ($asset) {
            $role = $asset['type'] === 'main_image' ? 'main_image' : 'gallery';
            return config('pim.copy_http_media')
                ? app(PimHttpMediaImporter::class)->import($asset['url'], $role)
                : ['url' => $asset['url'], 'role' => $role];
        }, $assets);
        return [
            'pim_media' => $media,
            'image' => collect($media)->firstWhere('role', 'main_image')['url'] ?? ($media[0]['url'] ?? null),
        ];
    }
}
