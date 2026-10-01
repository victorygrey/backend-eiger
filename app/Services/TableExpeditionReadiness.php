<?php

namespace App\Services;

use App\Models\Product;
use App\Support\PimMediaUrl;

class TableExpeditionReadiness
{
    /** @return array<string, mixed> */
    public function inspect(?Product $product): array
    {
        if (! $product) {
            return [
                'ready' => false,
                'score' => 0,
                'checks' => [],
                'missing' => ['Produk tidak terhubung'],
                'video_url' => null,
            ];
        }

        $product->loadMissing([
            'variants',
            'technologiesRelation',
            'specificationsRelation',
            'customAttributesRelation',
            'mediaRelation',
        ]);

        $checks = [
            'image' => filled($product->image),
            'description' => filled(strip_tags((string) $product->description)),
            'details' => $product->technologiesRelation->isNotEmpty()
                || $product->specificationsRelation->isNotEmpty()
                || $product->customAttributesRelation->isNotEmpty(),
            'variants' => $product->variants->isNotEmpty(),
        ];
        $labels = [
            'image' => 'Foto produk',
            'description' => 'Deskripsi produk',
            'details' => 'Fitur atau spesifikasi',
            'variants' => 'Varian CARE',
        ];
        $completed = collect($checks)->filter()->count();

        return [
            'ready' => $completed === count($checks),
            'score' => (int) round(($completed / count($checks)) * 100),
            'checks' => $checks,
            'missing' => collect($checks)
                ->filter(fn (bool $complete): bool => ! $complete)
                ->keys()
                ->map(fn (string $key): string => $labels[$key])
                ->values()
                ->all(),
            'video_url' => $this->videoUrl($product),
        ];
    }

    public function videoUrl(Product $product): ?string
    {
        foreach ($product->pim_media as $media) {
            $url = is_string($media) ? $media : ($media['url'] ?? $media['value'] ?? null);
            $hint = is_array($media) ? strtolower((string) ($media['type'] ?? $media['mime'] ?? '')) : '';
            if (is_string($url) && (str_contains($hint, 'video') || preg_match('/\.(mp4|webm|mov)(?:\?|$)/i', $url))) {
                return PimMediaUrl::toPublicUrl($url);
            }
        }

        return null;
    }
}
