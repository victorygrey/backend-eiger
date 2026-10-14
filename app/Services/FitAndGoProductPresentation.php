<?php

namespace App\Services;

use App\Models\Product;
use App\Support\DeviceProductPayload;
use App\Support\PimMediaUrl;
use Illuminate\Support\Collection;

class FitAndGoProductPresentation
{
    public static function relations(): array
    {
        return ['fitAndGoConfiguration', 'mediaRelation', 'variants', 'pimRecord'];
    }

    /** Stable gallery identity across the NAS/staging/local hostnames. */
    public function imageKey(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/api/pim-media/')
            ? $path
            : $url;
    }

    /** @return list<array{value: string, url: string}> */
    public function photos(Product $product): array
    {
        $product->loadMissing(self::relations());
        $urls = [$product->image];
        $storedPhotos = [];

        foreach ($product->pim_media as $media) {
            if (! is_array($media)) {
                continue;
            }
            $hint = strtolower((string) ($media['mime'] ?? $media['type'] ?? ''));
            $role = strtolower((string) ($media['role'] ?? 'gallery'));
            $url = $media['url'] ?? $media['value'] ?? null;
            if (is_string($url) && ! str_contains($hint, 'video')
                && in_array($role, ['main_image', 'gallery', 'image'], true)
                && ! preg_match('/\.(mp4|webm|mov|mp3|wav|ogg|pdf)(?:\?|$)/i', $url)) {
                $storedPhotos[] = $url;
            }
        }
        $urls = [...$urls, ...$storedPhotos, ...$product->variants->pluck('image')->all()];

        // Use raw PIM image URLs only when no stored gallery copies exist.
        if ($storedPhotos === []) {
            foreach (['generic', 'variant'] as $kind) {
                foreach ($product->pim_image_payload[$kind] ?? [] as $item) {
                    foreach ($item['image'] ?? [] as $photo) {
                        $urls[] = $photo['url'] ?? null;
                    }
                }
            }
        }

        return collect($urls)
            ->filter(fn (mixed $url): bool => is_string($url) && trim($url) !== '')
            ->map(fn (string $url): string => $this->imageKey($url))
            ->unique()->values()
            ->map(fn (string $key): array => ['value' => $key, 'url' => PimMediaUrl::toPublicUrl($key)])
            ->all();
    }

    public function isConfigured(Product $product): bool
    {
        return collect($this->colors($product))->every('configured');
    }

    public function colorKey(?string $color): string
    {
        $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $color)));

        return $normalized === '' ? 'default' : hash('sha256', $normalized);
    }

    /** Sizes sharing a color share one presentation; untagged photos remain available manually. */
    public function colors(Product $product): array
    {
        $photos = $this->photos($product);
        $groups = [];
        $owners = [];
        $bySku = [];
        $byId = [];
        foreach ($product->variants as $variant) {
            $key = $this->colorKey($variant->color);
            $groups[$key] ??= ['key' => $key, 'name' => trim((string) $variant->color) ?: 'Tanpa warna', 'variant_skus' => []];
            $groups[$key]['variant_skus'][] = $variant->sku;
            $bySku[$variant->sku] = $byId[$variant->id] = $key;
            if ($variant->image) {
                $owners[$this->imageKey($variant->image)][$key] = true;
            }
        }
        if ($groups === []) {
            $groups['default'] = ['key' => 'default', 'name' => 'Produk', 'variant_skus' => []];
        }
        foreach ($product->mediaRelation as $media) {
            $key = $byId[$media->product_variant_id] ?? $bySku[$media->sku] ?? null;
            if ($key !== null && $media->url) {
                $owners[$this->imageKey($media->url)][$key] = true;
            }
        }
        foreach ($product->pim_image_payload['variant'] ?? [] as $item) {
            $key = $bySku[$item['sku'] ?? ''] ?? null;
            if ($key !== null) {
                foreach ($item['image'] ?? [] as $photo) {
                    if (is_string($photo['url'] ?? null)) {
                        $owners[$this->imageKey($photo['url'])][$key] = true;
                    }
                }
            }
        }

        $config = $product->fitAndGoConfiguration;
        $saved = $config?->colors;
        foreach ($groups as $key => &$group) {
            $group['photos'] = array_values(array_filter($photos, fn (array $photo): bool => ! isset($owners[$photo['value']]) || isset($owners[$photo['value']][$key])));
            // A previous product-wide choice is safe only when there is a single color.
            $selection = $saved[$key] ?? ($saved === null && count($groups) === 1 && $config
                ? ['image' => $config->image, 'description' => $config->description] : []);
            $group['image'] = $selection['image'] ?? null;
            $group['description'] = $selection['description'] ?? '';
            $allowed = array_column($group['photos'], 'value');
            $group['photo_descriptions'] = array_intersect_key($selection['photo_descriptions'] ?? [], array_flip($allowed));
            if (in_array($group['image'], $allowed, true)) {
                $group['photo_descriptions'][$group['image']] = $group['description'];
            }
            $group['configured'] = trim($group['description']) !== ''
                && in_array($group['image'], array_column($group['photos'], 'value'), true);
        }
        unset($group);

        return array_values($groups);
    }

    public function primaryImage(Product $product): ?string
    {
        return $this->colors($product)[0]['image'] ?? $product->image;
    }

    public function ready(Collection $products): Collection
    {
        return $products->filter(fn (Product $product): bool => $this->isConfigured($product))->values();
    }

    /** Product master data stays intact; only the Fit & Go output is customized. */
    public function payload(Product $product): array
    {
        $payload = DeviceProductPayload::make($product);
        $colors = collect($this->colors($product))->keyBy('key');
        $primary = $colors->first();
        $image = app(DeviceImageWebpService::class)->url($primary['image']);
        $payload['image'] = $image;
        $payload['description'] = $primary['description'];
        $payload['media'] = array_values(array_filter(
            $payload['media'], fn (array $media): bool => ($media['url'] ?? null) !== $image,
        ));
        foreach ($product->variants as $index => $variant) {
            $color = $colors->get($this->colorKey($variant->color));
            $payload['variants'][$index]['image'] = app(DeviceImageWebpService::class)->url($color['image']);
            $payload['variants'][$index]['description'] = $color['description'];
        }

        return $payload;
    }
}
