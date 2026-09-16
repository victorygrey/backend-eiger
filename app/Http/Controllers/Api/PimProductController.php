<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\PimCareProductMapper;
use App\Services\PimPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PimProductController extends Controller
{
    public function store(Request $request, PimPayload $service, PimCareProductMapper $mapper)
    {
        abort_unless(config('pim.legacy_http_enabled'), 404);
        $token = config('pim.inbound_token');
        abort_unless(is_string($token) && $token !== '' && hash_equals($token, $request->bearerToken() ?? ''), 401);
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
            foreach ($detail['variant'] as $pimVariant) {
                $existingLegacyProduct = Product::where('sku', $pimVariant['sku'])->first();
                if ($existingLegacyProduct && $existingLegacyProduct->sku !== $detail['generic']) {
                    $existingLegacyProduct->update(array_merge($media[$pimVariant['sku']], [
                        'name' => $pimVariant['name'],
                        'description' => $catalog['description'],
                        'pim_payload' => $detail,
                        'pim_image_payload' => $data['image'],
                        'pim_catalog_active' => true,
                    ]));
                }
            }

            $genericSku = $detail['generic'] ?? null;
            if ($genericSku) {
                $parentValues = [
                    'name' => $catalog['name'] ?: ($detail['variant'][0]['name'] ?? 'EIGER Product'),
                    'zone_id' => $catalog['zone_id'],
                    'material' => $catalog['material'],
                    'description' => $catalog['description'],
                    'pim_payload' => $detail,
                    'pim_image_payload' => $data['image'],
                    'pim_catalog_active' => true,
                    'pim_media' => $genericMedia['pim_media'],
                    'image' => $genericMedia['image'] ?? ($detail['mainImage'] ?? null),
                ];
                if ($catalog['care_found']) {
                    $parentValues['price'] = $catalog['price'];
                    $parentValues['stock'] = $catalog['stock'];
                }
                $parent = Product::updateOrCreate(['sku' => $genericSku], $parentValues);

                $syncedSkus = [];
                foreach ($catalog['variants'] as $variant) {
                    $syncedSkus[] = $variant['sku'];
                    $parent->variants()->updateOrCreate(
                        ['sku' => $variant['sku']],
                        [
                            'name'  => $variant['name'],
                            'color' => $variant['color'] ?? null,
                            'size'  => $variant['size'] ?? null,
                            'ecmsku' => $variant['ecmsku'] ?? null,
                            'moq' => $variant['moq'] ?? null,
                            'custom_attributes' => $variant['customAttributes'] ?? [],
                            'price' => $variant['price'] ?? $parent->price,
                            'stock' => $variant['stock'] ?? 0,
                            // Prefer the CMS copy. CARE variants often inherit an
                            // article-level PIM URL that is unreachable outside LAN.
                            'image' => $media[$variant['sku']]['image']
                                ?? $genericMedia['image']
                                ?? $variant['image']
                                ?? $parent->image,
                        ]
                    );
                }
                if ($catalog['care_found']) {
                    $syncedSkus === []
                        ? $parent->variants()->delete()
                        : $parent->variants()->whereNotIn('sku', $syncedSkus)->delete();
                }

            }
            SyncLog::create([
                'source' => 'pim', 'status' => 'success',
                'message' => 'PIM article '.$detail['generic'].': '.count($catalog['variants']).' CARE variants synchronized.',
                'synced_at' => now(),
            ]);
            return count($catalog['variants']);
        });
        return response()->json(['status' => true, 'message' => 'PIM products synchronized', 'data' => ['synced' => $count]]);
    }
}
