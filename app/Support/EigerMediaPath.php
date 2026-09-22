<?php

namespace App\Support;

use Illuminate\Support\Str;

class EigerMediaPath
{
    public static function productFolder(array $context): string
    {
        $name = Str::slug((string) ($context['product_name'] ?? 'product')) ?: 'product';
        $sku = Str::slug((string) ($context['generic_sku'] ?? $context['sku'] ?? 'unknown')) ?: 'unknown';

        return $name.'--'.$sku;
    }

    public static function directory(string $mime, string $role, array $context = []): string
    {
        $product = self::productFolder($context);
        if (str_starts_with($mime, 'video/')) {
            return self::isLedAmbience($role, $context)
                ? 'led-ambience/videos/'.$product
                : 'products/videos/'.$product;
        }

        return 'products/photos/'.$product;
    }

    public static function isLedAmbience(string $role, array $context = []): bool
    {
        if (($context['scope'] ?? null) === 'led-ambience') {
            return true;
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $role) ?? '');

        return str_contains($normalized, 'led') && str_contains($normalized, 'ambience');
    }

    public static function publicUrl(string $relativePath): string
    {
        return '/api/pim-media/'.str_replace(DIRECTORY_SEPARATOR, '/', ltrim($relativePath, '/\\'));
    }
}
