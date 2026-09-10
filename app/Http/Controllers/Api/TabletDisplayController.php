<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tablet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TabletDisplayController extends Controller
{
    public function activate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:100'],
            'activation_code' => ['required', 'string', 'max:64'],
        ]);

        $tablet = Tablet::where('slug', $validated['slug'])->where('is_active', true)->first();

        if (! $tablet || ! Hash::check($validated['activation_code'], $tablet->activation_code_hash)) {
            return response()->json(['message' => 'Kode aktivasi tidak valid.'], 401);
        }

        $token = Str::random(64);
        $tablet->forceFill([
            'device_token_hash' => Hash::make($token),
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'token' => $token,
            'tablet' => ['slug' => $tablet->slug, 'name' => $tablet->name],
        ]);
    }

    public function show(Request $request, Tablet $tablet): JsonResponse
    {
        if (! $this->authorized($request, $tablet)) {
            return response()->json(['message' => 'Aktivasi perangkat diperlukan.'], 401);
        }

        if (! $tablet->is_active || ! $tablet->featured_product_id) {
            return response()->json(['message' => 'Konfigurasi tablet belum aktif atau belum lengkap.'], 404);
        }

        $tablet->load(['featuredProduct.zone', 'recommendations.zone']);

        return response()->json([
            'tablet' => [
                'id' => (string) $tablet->id,
                'slug' => $tablet->slug,
                'name' => $tablet->name,
                'location' => $tablet->location ?? '',
            ],
            'version' => $tablet->config_version,
            'featured' => $this->productPayload($tablet->featuredProduct),
            'recommendations' => $tablet->recommendations
                ->reject(fn (Product $product) => $product->is_discontinued)
                ->map(fn (Product $product) => $this->productPayload($product))
                ->values(),
            'publishedAt' => $tablet->updated_at?->toISOString(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function heartbeat(Request $request, Tablet $tablet): JsonResponse
    {
        if (! $this->authorized($request, $tablet)) {
            return response()->json(['message' => 'Token perangkat tidak valid.'], 401);
        }

        $validated = $request->validate([
            'version' => ['nullable', 'integer', 'min:1'],
            'media_status' => ['nullable', 'string', 'in:ready,error,loading'],
        ]);

        $tablet->forceFill([
            'last_seen_at' => now(),
            'media_status' => $validated['media_status'] ?? $tablet->media_status,
        ])->save();

        return response()->json([
            'ok' => true,
            'config_version' => $tablet->config_version,
            'refresh_required' => isset($validated['version']) && (int) $validated['version'] !== $tablet->config_version,
        ]);
    }

    private function authorized(Request $request, Tablet $tablet): bool
    {
        $token = $request->bearerToken() ?: $request->query('token');

        return is_string($token)
            && $token !== ''
            && is_string($tablet->device_token_hash)
            && Hash::check($token, $tablet->device_token_hash);
    }

    private function productPayload(Product $product): array
    {
        $image = $product->image;
        if (is_string($image) && str_starts_with($image, '/api/pim-media/')) {
            $image = url($image);
        }

        $media = collect($product->pim_media ?? [])->map(function ($item) {
            if (! is_array($item)) {
                return null;
            }
            $url = $item['url'] ?? null;
            if (is_string($url) && str_starts_with($url, '/')) {
                $url = url($url);
            }

            return [
                'type' => str_starts_with((string) ($item['mime'] ?? ''), 'video/') ? 'video' : 'image',
                'role' => $item['role'] ?? 'image',
                'url' => $url,
            ];
        })->filter(fn ($item) => is_array($item) && is_string($item['url']))->values()->all();

        return [
            'id' => (string) $product->id,
            'slug' => Str::slug($product->name).'-'.$product->id,
            'name' => $product->name,
            'category' => $product->zone?->name ?? 'EIGER Product',
            'sku' => $product->sku,
            'activity' => $product->zone?->name ?? 'Daily Wear',
            'gender' => 'UNISEX',
            'weight' => '—',
            'dimension' => '—',
            'materials' => $product->material ? [$product->material] : ['—'],
            'description' => $product->description ?: 'Informasi produk akan diperbarui melalui CMS.',
            'features' => [
                'Produk resmi EIGER untuk aktivitas harian dan luar ruang.',
                'Detail lengkap mengikuti informasi terbaru dari CMS.',
            ],
            'care' => [
                'Ikuti petunjuk perawatan pada label produk.',
                'Simpan di tempat kering setelah digunakan.',
            ],
            'imageUrl' => $image ?: '/products/bogota.jpg',
            'media' => $media,
        ];
    }
}
