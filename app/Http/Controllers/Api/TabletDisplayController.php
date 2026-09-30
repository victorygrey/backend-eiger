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
            'activation_code' => ['required', 'string', 'max:64'],
        ]);

        $submittedCode = (string) $validated['activation_code'];
        $activationCode = Tablet::normalizeActivationCode($submittedCode);
        $lookupHash = Tablet::activationCodeLookupHash($activationCode);
        $tablet = Tablet::where('activation_code_lookup_hash', $lookupHash)
            ->where('is_active', true)
            ->first();

        // Existing installations only have the bcrypt hash. Upgrade the matching
        // row once, then all future activations use the indexed lookup above.
        if (! $tablet) {
            $tablet = Tablet::whereNull('activation_code_lookup_hash')
                ->where('is_active', true)
                ->get()
                ->first(fn (Tablet $candidate): bool => Hash::check($activationCode, $candidate->activation_code_hash)
                    || ($submittedCode !== $activationCode && Hash::check($submittedCode, $candidate->activation_code_hash)));

            if ($tablet) {
                $tablet->forceFill([
                    'activation_code_hash' => Hash::make($activationCode),
                    'activation_code_lookup_hash' => $lookupHash,
                ])->save();
            }
        }

        if (! $tablet || ! Hash::check($activationCode, $tablet->activation_code_hash)) {
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

        if (! $tablet->featuredProduct?->isAvailableForInteractiveTablet()) {
            return response()->json(['message' => 'Produk utama tablet sudah tidak aktif pada List Product.'], 404);
        }

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
                ->filter(fn (Product $product) => $product->isAvailableForInteractiveTablet())
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
        return DeviceProductPayload::make($product);
    }
}
