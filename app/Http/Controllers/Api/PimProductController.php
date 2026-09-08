<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PimProductController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(config('pim.legacy_http_enabled'), 404);
        $token = config('pim.inbound_token');
        abort_unless(is_string($token) && $token !== '' && hash_equals($token, $request->bearerToken() ?? ''), 401);
        $data = $request->validate([
            'product' => 'required|array',
            'product.generic' => 'required|string|max:255',
            'product.name' => 'required|string|max:255',
            'product.mainImage' => 'nullable|url|max:255',
            'product.customAttributes' => 'sometimes|array',
            'product.customAttributes.*.attributeCode' => 'required|string',
            'product.customAttributes.*.value' => 'nullable|string',
            'product.variant' => 'required|array|min:1',
            'product.variant.*.sku' => 'required|string|max:255|distinct',
            'product.variant.*.name' => 'required|string|max:255',
            'image' => 'required|array',
            'image.variant' => 'sometimes|array',
            'image.variant.*.sku' => 'required|string',
            'image.variant.*.image' => 'required|array',
            'image.variant.*.image.*.type' => 'required|string',
            'image.variant.*.image.*.url' => 'required|url|max:255',
        ]);
        if ($request->header('X-Simulate-Atom-Failure') === 'true') {
            return response()->json(['status' => false, 'message' => 'Simulated CMS integration failure'], 500);
        }
        $count = DB::transaction(function () use ($data) {
            $detail = $data['product'];
            $attributes = collect($detail['customAttributes'] ?? [])->pluck('value', 'attributeCode');
            $images = collect($data['image']['variant'] ?? [])->keyBy('sku');
            foreach ($detail['variant'] as $variant) {
                $values = ['name' => $variant['name']];
                $image = collect($images->get($variant['sku'])['image'] ?? [])->firstWhere('type', 'main_image');
                if ($image || array_key_exists('mainImage', $detail)) {
                    $values['image'] = $image['url'] ?? $detail['mainImage'];
                }
                if ($attributes->has('long_description') || $attributes->has('short_description')) {
                    $values['description'] = $attributes->get('long_description') ?: $attributes->get('short_description');
                }
                // SKU variants are sellable products. CARE retains price/stock ownership.
                Product::updateOrCreate(['sku' => $variant['sku']], $values);
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
