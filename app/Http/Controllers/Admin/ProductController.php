<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Zone;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a paginated list of products with search and filtering.
     */
    public function index(Request $request)
    {
        $products = Product::with(['zone', 'variants'])
            ->withCount('variants')
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', "%{$request->search}%")
                        ->orWhere('sku', 'like', "%{$request->search}%")
                        ->orWhereHas('variants', function ($vq) use ($request) {
                            $vq->where('sku', 'like', "%{$request->search}%")
                                ->orWhere('name', 'like', "%{$request->search}%")
                                ->orWhere('color', 'like', "%{$request->search}%");
                        });
                });
            })
            ->when($request->filled('zone_id'), fn($q) => $q->where('zone_id', $request->zone_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $zones = Zone::all();

        return view('admin.products.index', compact('products', 'zones'));
    }

    /**
     * Show the form for creating a new product.
     */
    public function pimLookup(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']]);
        try { return response()->json(app(\App\Services\PimProductLookup::class)->get($data['code'])); }
        catch (\Illuminate\Validation\ValidationException $e) { throw $e; }
        catch (\Throwable $e) { return response()->json(['message' => 'PIM tidak dapat dihubungi atau payload belum tersedia.'], 502); }
    }

    /**
     * Unified Catalog Lookup from PIM, CARE, and Scraping data.
     */
    public function catalogLookup(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']]);
        $code = trim($data['code']);

        $result = [
            'sku' => $code,
            'name' => '',
            'category' => '',
            'description' => '',
            'material' => '',
            'image' => '',
            'price' => 0,
            'stock' => 0,
            'variants' => [],
        ];

        // 1. Try local scrap-eiger products.json first
        $scrapJsonPath = 'd:/LAPTOP FAIZAL/_Project/scrap-eiger/data/products.json';
        if (file_exists($scrapJsonPath)) {
            $products = json_decode(file_get_contents($scrapJsonPath), true) ?: [];
            foreach ($products as $p) {
                $sSku = (string) ($p['product_code'] ?? $p['sku'] ?? '');
                if ($sSku === $code) {
                    $result['name'] = $p['product_name'] ?? '';
                    $result['category'] = $p['category'] ?? '';
                    $result['description'] = $p['description'] ?? '';
                    $result['image'] = $p['images'][0]['url'] ?? '';
                    $result['price'] = (float) ($p['price'] ?? 0);

                    $colors = !empty($p['available_colors']) ? $p['available_colors'] : ['STD'];
                    $sizes = !empty($p['available_sizes']) ? $p['available_sizes'] : ['ALL'];
                    $seq = 1;
                    foreach ($colors as $c) {
                        foreach ($sizes as $sz) {
                            $sku12 = sprintf('%s%03d', $code, $seq);
                            $result['variants'][] = [
                                'sku' => $sku12,
                                'name' => sprintf('%s - %s - %s', $p['product_name'], $c, $sz),
                                'color' => $c,
                                'size' => $sz,
                                'price' => (float) ($p['price'] ?? 0),
                                'stock' => 15,
                            ];
                            $seq++;
                        }
                    }
                    break;
                }
            }
        }

        // 2. Query CARE Simulator for current store price & stock
        try {
            $baseUrl = config('services.care.url', 'http://127.0.0.1:8002');
            $serverKey = config('services.care.server_key');
            $storeCode = config('services.care.store_code', '2022');
            $headers = ['Accept' => 'application/json'];
            if ($serverKey) $headers['x-server-key'] = $serverKey;

            // Pricing
            $pResp = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders($headers)
                ->get(rtrim($baseUrl, '/') . '/api/server/pricing_details', ['filter' => ['skucode' => $code, 'loccode' => $storeCode]]);
            if ($pResp->successful()) {
                $pData = $pResp->json('data') ?? [];
                if (!empty($pData[0]['articleprice'])) {
                    $result['price'] = (float) $pData[0]['articleprice'];
                }
            }

            // Stocks
            $sResp = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders($headers)
                ->get(rtrim($baseUrl, '/') . '/api/server/stocks', ['filter' => ['loccode' => $storeCode]]);
            if ($sResp->successful()) {
                $sData = collect($sResp->json('data') ?? [])->keyBy('skucode');
                if (isset($sData[$code])) {
                    $result['stock'] = (int) $sData[$code]['stock'];
                }
                foreach ($result['variants'] as &$v) {
                    if (isset($sData[$v['sku']])) {
                        $v['stock'] = (int) $sData[$v['sku']]['stock'];
                    }
                }
                unset($v);
            }
        } catch (\Throwable $e) {
            // ignore CARE lookup failure
        }

        // 3. Fallback to PIM Lookup if name is still empty
        if (empty($result['name'])) {
            try {
                $pim = app(\App\Services\PimProductLookup::class)->get($code);
                if (!empty($pim['product'])) {
                    $result['name'] = $pim['product']['name'] ?? '';
                    $result['image'] = $pim['product']['mainImage'] ?? '';
                    foreach ($pim['product']['customAtributes'] ?? [] as $ca) {
                        if (($ca['attributeCode'] ?? '') === 'long_description' || ($ca['attributeCode'] ?? '') === 'short_description') {
                            $result['description'] = $ca['value'] ?? '';
                        }
                    }
                    foreach ($pim['product']['variant'] ?? [] as $pv) {
                        $result['variants'][] = [
                            'sku' => $pv['sku'],
                            'name' => $pv['name'],
                            'color' => $pv['color'] ?? '',
                            'size' => $pv['size'] ?? '',
                            'price' => $result['price'],
                            'stock' => 15,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if (empty($result['name'])) {
            return response()->json(['message' => 'Produk dengan kode ' . $code . ' tidak ditemukan di katalog PIM maupun CARE.'], 404);
        }

        return response()->json($result);
    }

    public function create()
    {
        $zones = Zone::all();
        return view('admin.products.create', compact('zones'));
    }

    /**
     * Store a newly created product.
     */
    public function store(StoreProductRequest $request)
    {
        $data = app(\App\Services\PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        $variants = $data['variants'] ?? [];
        unset($data['variants']);

        $product = Product::create($data);

        if (! empty($variants)) {
            foreach ($variants as $var) {
                if (! empty($var['sku'])) {
                    $product->variants()->create([
                        'sku'   => $var['sku'],
                        'name'  => $var['name'] ?? ($product->name . ' - ' . ($var['color'] ?? '') . ' - ' . ($var['size'] ?? '')),
                        'color' => $var['color'] ?? null,
                        'size'  => $var['size'] ?? null,
                        'price' => $var['price'] ?? $product->price,
                        'stock' => $var['stock'] ?? 0,
                    ]);
                }
            }
            $product->update(['stock' => $product->variants()->sum('stock')]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product)
    {
        $product->load('variants');
        $zones = Zone::all();
        return view('admin.products.edit', compact('product', 'zones'));
    }

    /**
     * Update the specified product.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = app(\App\Services\PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        $variants = $data['variants'] ?? null;
        unset($data['variants']);

        $product->update($data);

        if ($variants !== null) {
            $existingSku = [];
            foreach ($variants as $var) {
                if (! empty($var['sku'])) {
                    $existingSku[] = $var['sku'];
                    $product->variants()->updateOrCreate(
                        ['sku' => $var['sku']],
                        [
                            'name'  => $var['name'] ?? ($product->name . ' - ' . ($var['color'] ?? '') . ' - ' . ($var['size'] ?? '')),
                            'color' => $var['color'] ?? null,
                            'size'  => $var['size'] ?? null,
                            'price' => $var['price'] ?? $product->price,
                            'stock' => $var['stock'] ?? 0,
                        ]
                    );
                }
            }
            if (! empty($existingSku)) {
                $product->variants()->whereNotIn('sku', $existingSku)->delete();
            }
            $product->update(['stock' => $product->variants()->sum('stock')]);
        }

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
