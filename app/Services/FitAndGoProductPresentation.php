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
        return ['fitAndGoConfiguration', 'mediaRelation', 'variants'];
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
        $config = $product->fitAndGoConfiguration;

        return $config !== null
            && trim((string) $config->description) !== ''
            && in_array($config->image, array_column($this->photos($product), 'value'), true);
    }

    public function ready(Collection $products): Collection
    {
        return $products->filter(fn (Product $product): bool => $this->isConfigured($product))->values();
    }

    /** Product master data stays intact; only the Fit & Go output is customized. */
    public function payload(Product $product): array
    {
        $payload = DeviceProductPayload::make($product);
        $config = $product->fitAndGoConfiguration;
        $image = app(DeviceImageWebpService::class)->url($config->image);
        $payload['image'] = $image;
        $payload['description'] = $config->description;
        $payload['media'] = array_values(array_filter(
            $payload['media'], fn (array $media): bool => ($media['url'] ?? null) !== $image,
        ));
        foreach ($product->variants as $index => $variant) {
            if (! $variant->image) {
                $payload['variants'][$index]['image'] = $image;
            }
        }

        return $payload;
    }
}
