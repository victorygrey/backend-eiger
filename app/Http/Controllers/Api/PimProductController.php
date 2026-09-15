<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\PimPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PimProductController extends Controller
{
    public function store(Request $request, PimPayload $service)
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
        $count = DB::transaction(function () use ($data, $media) {
            $detail = $data['product'];
            $attributes = collect($detail['customAtributes'])->pluck('value', 'attributeCode');
            foreach ($detail['variant'] as $variant) {
                $values = array_merge($media[$variant['sku']], [
                    'name' => $variant['name'],
                    'pim_payload' => $detail,
                    'pim_image_payload' => $data['image'],
                ]);
                if ($attributes->has('long_description') || $attributes->has('short_description')) {
                    $values['description'] = $attributes->get('long_description') ?: $attributes->get('short_description');
                }
                // CARE retains price, stock, zone and RFID ownership.
                Product::updateOrCreate(['sku' => $variant['sku']], $values);
            }

            // Also ensure generic parent product exists and links variants
            $genericSku = $detail['generic'] ?? null;
            if ($genericSku) {
                $parentValues = [
                    'name' => $detail['name'] ?? ($detail['variant'][0]['name'] ?? 'EIGER Product'),
                    'pim_payload' => $detail,
                    'pim_image_payload' => $data['image'],
                    'image' => $detail['mainImage'] ?? null,
                ];
                if ($attributes->has('long_description') || $attributes->has('short_description')) {
                    $parentValues['description'] = $attributes->get('long_description') ?: $attributes->get('short_description');
                }
                if ($attributes->has('material')) $parentValues['material'] = $attributes->get('material');

                $parent = Product::updateOrCreate(['sku' => $genericSku], $parentValues);

                foreach ($detail['variant'] as $variant) {
                    $parent->variants()->updateOrCreate(
                        ['sku' => $variant['sku']],
                        [
                            'name'  => $variant['name'],
                            'color' => $variant['color'] ?? null,
                            'size'  => $variant['size'] ?? null,
                            'image' => $media[$variant['sku']]['image'] ?? $parent->image,
                        ]
                    );
                }
            }
            SyncLog::create([
                'source' => 'pim', 'status' => 'success',
                'message' => 'PIM article '.$detail['generic'].': '.count($detail['variant']).' variants synchronized.',
                'synced_at' => now(),
            ]);
            return count($detail['variant']);
        });
        return response()->json(['status' => true, 'message' => 'PIM products synchronized', 'data' => ['synced' => $count]]);
    }
}
