<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Tablet;
use App\Support\DeviceProductPayload;
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

        $tablet->load(array_merge(
            array_map(fn (string $relation): string => 'featuredProduct.'.$relation, DeviceProductPayload::relations()),
            array_map(fn (string $relation): string => 'recommendations.'.$relation, DeviceProductPayload::relations())
        ));

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
        $payload = DeviceProductPayload::make($product);
        $activity = collect($payload['activities'])
            ->first(fn (array $item): bool => (bool) ($item['selected'] ?? false))
            ?? collect($payload['activities'])->first();
        $features = collect($payload['technologies'])->map(function (array $technology): string {
            $name = $technology['name'] ?? '';
            $description = $technology['description'] ?? '';

            return trim($name.($name && $description ? ': ' : '').$description);
        })->filter()->values()->all();

        if ($features === []) {
            $features = ['Detail produk mengikuti informasi terbaru dari CMS.'];
        }

        $dimensions = collect($payload['specifications'])
            ->filter(fn (array $specification): bool => str_contains(strtolower((string) ($specification['code'] ?? $specification['name'] ?? '')), 'dimension'))
            ->pluck('value')
            ->filter()
            ->implode(' × ');

        return array_merge($payload, [
            'id' => (string) $product->id,
            'activity' => $activity['name'] ?? $product->zone?->name ?? 'Daily Wear',
            'gender' => $product->gender ?: 'UNISEX',
            'weight' => $payload['weight'] ?? '—',
            'dimension' => $dimensions ?: '—',
            'materials' => $product->material ? [$product->material] : ['—'],
            'description' => $product->description ?: 'Informasi produk akan diperbarui melalui CMS.',
            'features' => $features,
            'care' => [
                'Ikuti petunjuk perawatan pada label produk.',
                'Simpan di tempat kering setelah digunakan.',
            ],
            'imageUrl' => $payload['image'] ?: '/products/bogota.jpg',
        ]);
    }
}
