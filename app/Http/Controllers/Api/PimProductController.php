<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\PimCareProductMapper;
use App\Services\PimPayload;
use App\Services\PimProductDataStore;
use App\Services\PimInboundTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PimProductController extends Controller
{
    public function store(Request $request, PimPayload $service, PimCareProductMapper $mapper, PimInboundTokenService $tokens)
    {
        abort_unless(config('pim.legacy_http_enabled'), 404);
        abort_unless($tokens->authenticate($request->bearerToken()), 401);
        $data = $service->validate($request->only('product', 'image'));
        if ($request->header('X-Simulate-Atom-Failure') === 'true') {
            return response()->json(['status' => false, 'message' => 'Simulated CMS integration failure'], 500);
        }
        // Finish all downloads before the transaction; a failed image never partially updates the catalog.
        $media = [];
        foreach ($data['product']['variant'] as $variant) {
            $media[$variant['sku']] = $service->mediaValues($data['product'], $data['image'], $variant['sku']);
        }
        $genericMedia = $service->mediaValues($data['product'], $data['image'], $data['product']['generic']);
        $catalog = $mapper->map($data['product'], $data['image']);
        $count = DB::transaction(function () use ($data, $media, $genericMedia, $catalog) {
            $detail = $data['product'];
            $dataStore = app(PimProductDataStore::class);
            foreach ($detail['variant'] as $pimVariant) {
                $existingLegacyProduct = Product::where('sku', $pimVariant['sku'])->first();
                if ($existingLegacyProduct && $existingLegacyProduct->sku !== $detail['generic']) {
                    $existingLegacyProduct->update([
                        'name' => $pimVariant['name'],
                        'description' => $catalog['description'],
                        'pim_catalog_active' => true,
                        'image' => $media[$pimVariant['sku']]['image'] ?? $existingLegacyProduct->image,
                    ]);
                    $dataStore->replace($existingLegacyProduct, $detail, $data['image'],
                        $media[$pimVariant['sku']]['pim_media'] ?? [], source: 'pim-http');
                }
            }

            $genericSku = $detail['generic'] ?? null;
            if ($genericSku) {
                $parentValues = [
                    'name' => $catalog['name'] ?: ($detail['variant'][0]['name'] ?? 'EIGER Product'),
                    'zone_id' => $catalog['zone_id'],
                    'material' => $catalog['material'],
                    'description' => $catalog['description'],
                    'pim_catalog_active' => true,
                    'image' => $genericMedia['image'] ?? ($detail['mainImage'] ?? null),
                ];
                if ($catalog['care_found']) {
                    $parentValues['price'] = $catalog['price'];
                    $parentValues['stock'] = $catalog['stock'];
                    $parentValues['care_synced_at'] = now();
                }
                $parent = Product::updateOrCreate(['sku' => $genericSku], $parentValues);

                $syncedSkus = [];
                foreach ($catalog['variants'] as $variant) {
                    $syncedSkus[] = $variant['sku'];
                    $existingVariant = $parent->variants()->where('sku', $variant['sku'])->first();
                    $savedVariant = $parent->variants()->updateOrCreate(
                        ['sku' => $variant['sku']],
                        [
                            'name'  => $variant['name'],
                            'color' => $variant['color'] ?? null,
                            'size'  => $variant['size'] ?? null,
                            'ecmsku' => $variant['ecmsku'] ?? null,
                            'moq' => $variant['moq'] ?? null,
                            'price' => $catalog['care_found']
                                ? ($variant['price'] ?? $parent->price)
                                : ($existingVariant?->price ?? 0),
                            'stock' => $catalog['care_found']
                                ? ($variant['stock'] ?? 0)
                                : ($existingVariant?->stock ?? 0),
                            // Prefer the CMS copy. CARE variants often inherit an
                            // article-level PIM URL that is unreachable outside LAN.
                            'image' => $media[$variant['sku']]['image']
                                ?? $genericMedia['image']
                                ?? $variant['image']
                                ?? $parent->image,
                        ]
                    );
                    $dataStore->replaceVariantAttributes($savedVariant, $variant['customAttributes'] ?? []);
                }
                if ($catalog['care_found']) {
                    $syncedSkus === []
                        ? $parent->variants()->delete()
                        : $parent->variants()->whereNotIn('sku', $syncedSkus)->delete();
                }

                $dataStore->replace(
                    $parent,
                    $detail,
                    $data['image'],
                    $genericMedia['pim_media'],
                    source: 'pim-http',
                    variantMedia: collect($media)->map(fn ($item) => $item['pim_media'] ?? [])->all(),
                );

            }
            SyncLog::create([
                'source' => 'pim', 'status' => 'success',
                'message' => $catalog['care_found']
                    ? 'PIM article '.$detail['generic'].': '.count($catalog['variants']).' varian diperkaya dari CARE.'
                    : 'PIM article '.$detail['generic'].' diterima; CARE belum menyediakan harga/stok sehingga nilai komersial lama dipertahankan.',
                'synced_at' => now(),
            ]);
            return count($catalog['variants']);
        });
        return response()->json([
            'status' => true,
            'message' => $catalog['care_found']
                ? 'Produk PIM dan data komersial CARE berhasil disinkronkan.'
                : 'Produk PIM berhasil disimpan; data CARE belum ditemukan dan dapat disinkronkan ulang.',
            'data' => ['synced' => $count, 'care_enriched' => $catalog['care_found']],
        ]);
    }
}
