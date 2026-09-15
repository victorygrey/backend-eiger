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
            'zone_id' => null,
            'variants' => [],
        ];

        // 1. Try products.json (database/data/products.json or local fallback)
        $scrapJsonPath = database_path('data/products.json');
        if (!file_exists($scrapJsonPath)) {
            $scrapJsonPath = 'd:/LAPTOP FAIZAL/_Project/scrap-eiger/data/products.json';
        }

        $scrapedProduct = null;
        $images = [];
        if (file_exists($scrapJsonPath)) {
            $products = json_decode(file_get_contents($scrapJsonPath), true) ?: [];
            foreach ($products as $p) {
                $sSku = (string) ($p['product_code'] ?? $p['sku'] ?? '');
                if ($sSku === $code) {
                    $scrapedProduct = $p;
                    $result['name'] = $p['product_name'] ?? '';
                    $result['category'] = $p['category'] ?? '';
                    $result['description'] = $p['description'] ?? '';
                    $result['image'] = $p['images'][0]['url'] ?? '';
                    $result['price'] = (float) ($p['price'] ?? 0);

                    if (!empty($p['images'])) {
                        foreach ($p['images'] as $img) {
                            if (!empty($img['url'])) $images[] = $img['url'];
                        }
                    }

                    // Extract material from description
                    if (preg_match('/Material\s*:\s*([^.\r\n,]+)/i', $p['description'] ?? '', $m)) {
                        $result['material'] = trim($m[1]);
                    } elseif (preg_match('/Bahan\s*:\s*([^.\r\n,]+)/i', $p['description'] ?? '', $m)) {
                        $result['material'] = trim($m[1]);
                    }

                    // Build 12-digit variant SKUs (one individual row per size)
                    $colors = !empty($p['available_colors']) ? $p['available_colors'] : (!empty($p['color']) ? [$p['color']] : ['BLACK']);
                    $sizes = !empty($p['available_sizes']) ? $p['available_sizes'] : ['ALL'];
                    $seq = 1;
                    foreach ($colors as $c) {
                        foreach ($sizes as $sz) {
                            $sku12 = sprintf('%s%03d', $code, $seq++);
                            $result['variants'][] = [
                                'sku' => $sku12,
                                'name' => sprintf('%s - %s - %s', $result['name'], $c, $sz),
                                'color' => $c,
                                'size' => $sz,
                                'price' => $result['price'],
                                'stock' => 0,
                                'image' => $result['image'] ?? '',
                            ];
                        }
                    }
                    break;
                }
            }
        }

        // 2. Query PIM Simulator for official master data (if empty or to enrich)
        try {
            $pim = app(\App\Services\PimProductLookup::class)->get($code);
            if (!empty($pim['product'])) {
                if (empty($result['name'])) {
                    $result['name'] = $pim['product']['name'] ?? '';
                }
                if (empty($result['image'])) {
                    $result['image'] = $pim['product']['mainImage'] ?? '';
                }
                if (!empty($pim['product']['mainImage'])) {
                    $images[] = $pim['product']['mainImage'];
                }

                if (!empty($pim['product']['media'])) {
                    foreach ($pim['product']['media'] as $mediaGroup) {
                        foreach ($mediaGroup['files'] ?? [] as $f) {
                            if (!empty($f['value'])) $images[] = $f['value'];
                        }
                    }
                }

                if (!empty($pim['image']['generic'])) {
                    foreach ($pim['image']['generic'] as $gen) {
                        foreach ($gen['image'] ?? [] as $gi) {
                            if (!empty($gi['url'])) $images[] = $gi['url'];
                        }
                    }
                }

                if (!empty($pim['image']['variant'])) {
                    foreach ($pim['image']['variant'] as $pvar) {
                        foreach ($pvar['image'] ?? [] as $vi) {
                            if (!empty($vi['url'])) $images[] = $vi['url'];
                        }
                    }
                }

                foreach ($pim['product']['customAtributes'] ?? [] as $ca) {
                    $attrCode = strtolower($ca['attributeCode'] ?? '');
                    if (empty($result['description']) && in_array($attrCode, ['long_description', 'short_description'])) {
                        $result['description'] = $ca['value'] ?? '';
                    }
                    if (empty($result['material']) && in_array($attrCode, ['material', 'fabric', 'bahan'])) {
                        $result['material'] = trim($ca['value'] ?? '');
                    }
                    if (empty($result['category']) && $attrCode === 'category') {
                        $result['category'] = $ca['value'] ?? '';
                    }
                }

                // If material still empty, parse from description
                if (empty($result['material']) && !empty($result['description'])) {
                    if (preg_match('/Material\s*:\s*([^.\r\n,]+)/i', $result['description'], $m)) {
                        $result['material'] = trim($m[1]);
                    } elseif (preg_match('/Bahan\s*:\s*([^.\r\n,]+)/i', $result['description'], $m)) {
                        $result['material'] = trim($m[1]);
                    }
                }

                // If variants are still empty, build from PIM variants expanding any comma-separated sizes
                if (empty($result['variants'])) {
                    $seq = 1;
                    foreach ($pim['product']['variant'] ?? [] as $pv) {
                        $color = $pv['color'] ?? 'BLACK';
                        $rawSize = $pv['size'] ?? 'ALL';
                        $sizes = str_contains($rawSize, ',') ? array_map('trim', explode(',', $rawSize)) : [$rawSize];
                        foreach ($sizes as $sz) {
                            if (empty($sz)) continue;
                            $sku12 = sprintf('%s%03d', $code, $seq++);
                            $result['variants'][] = [
                                'sku' => $sku12,
                                'name' => sprintf('%s - %s - %s', $result['name'], $color, $sz),
                                'color' => $color,
                                'size' => $sz,
                                'price' => $result['price'],
                                'stock' => 0,
                                'image' => $result['image'] ?? '',
                            ];
                        }
                    }
                }

                // Attach raw PIM payloads and enrichment structures
                $result['pim_payload'] = $pim['product'];
                $result['pim_image_payload'] = $pim['image'] ?? null;
                $result['technology'] = $pim['product']['technology'] ?? [];
                $result['activity'] = $pim['product']['activity'] ?? [];
                $result['specification'] = $pim['product']['specification'] ?? [];
                $result['customAtributes'] = $pim['product']['customAtributes'] ?? [];
                if (!empty($pim['product']['weight'])) {
                    $result['weight'] = (int) $pim['product']['weight'];
                }
            }
        } catch (\Throwable $e) {
            // ignore PIM lookup failure
        }

        // Always ensure material is extracted if present in description
        if (empty($result['material']) && !empty($result['description'])) {
            if (preg_match('/Material\s*:\s*([^.\r\n,]+)/i', $result['description'], $m)) {
                $result['material'] = trim($m[1]);
            }
        }

        // 3. Query CARE Simulator for current store price & stock
        try {
            $baseUrl = app(\App\Services\CareSyncService::class)->getBaseUrl();
            $serverKey = config('services.care.server_key');
            $storeCode = config('services.care.store_code', '2022');
            $headers = ['Accept' => 'application/json'];
            if ($serverKey) $headers['x-server-key'] = $serverKey;

            // Fetch pricing for store
            $pResp = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders($headers)
                ->get(rtrim($baseUrl, '/') . '/api/server/pricing_details', ['filter' => ['loccode' => $storeCode]]);
            $pData = [];
            if ($pResp->successful()) {
                $pData = collect($pResp->json('data') ?? [])->keyBy('skucode');
                if (isset($pData[$code]['articleprice'])) {
                    $result['price'] = (float) $pData[$code]['articleprice'];
                }
            }

            // Fetch stocks for store
            $sResp = \Illuminate\Support\Facades\Http::timeout(3)->withHeaders($headers)
                ->get(rtrim($baseUrl, '/') . '/api/server/stocks', ['filter' => ['loccode' => $storeCode]]);
            $sData = [];
            if ($sResp->successful()) {
                $sData = collect($sResp->json('data') ?? [])->keyBy('skucode');
            }

            // Attach CARE prices & stocks to each variant
            foreach ($result['variants'] as &$v) {
                if (isset($pData[$v['sku']]['articleprice'])) {
                    $v['price'] = (float) $pData[$v['sku']]['articleprice'];
                } elseif ($result['price'] > 0) {
                    $v['price'] = $result['price'];
                }
                if (isset($sData[$v['sku']]['stock'])) {
                    $v['stock'] = (int) $sData[$v['sku']]['stock'];
                }
            }
            unset($v);

            // Accumulate parent stock
            $variantTotalStock = collect($result['variants'])->sum('stock');
            if ($variantTotalStock > 0) {
                $result['stock'] = $variantTotalStock;
            } elseif (isset($sData[$code]['stock'])) {
                $result['stock'] = (int) $sData[$code]['stock'];
            }
        } catch (\Throwable $e) {
            // ignore CARE lookup failure
        }

        // If no variants generated yet, generate at least 1 default 12-digit variant
        if (empty($result['variants'])) {
            $sku12 = sprintf('%s001', $code);
            $result['variants'][] = [
                'sku' => $sku12,
                'name' => sprintf('%s - STD - ALL', $result['name'] ?: 'Product'),
                'color' => 'STD',
                'size' => 'ALL',
                'price' => $result['price'],
                'stock' => $result['stock'],
                'image' => $result['image'] ?? '',
            ];
        }

        // Finalize unique images list
        $images = array_values(array_unique(array_filter($images)));
        if (empty($result['image']) && !empty($images)) {
            $result['image'] = $images[0];
        }
        $result['images'] = $images;

        // Map specific variant images from PIM if available
        if (!empty($pim['image']['variant'])) {
            $pimVarMap = [];
            foreach ($pim['image']['variant'] as $pvImg) {
                $pvSku = (string) ($pvImg['sku'] ?? '');
                $first = $pvImg['image'][0]['url'] ?? '';
                if ($pvSku && $first) {
                    $pimVarMap[$pvSku] = $first;
                }
            }
            foreach ($result['variants'] as &$v) {
                if (!empty($pimVarMap[$v['sku']])) {
                    $v['image'] = $pimVarMap[$v['sku']];
                } elseif (empty($v['image'])) {
                    $v['image'] = $result['image'] ?? '';
                }
            }
            unset($v);
        }

        // 4. Auto-detect Zone based on category & name
        $cat = strtolower($result['category'] . ' ' . $result['name']);
        $zoneId = null;
        if (preg_match('/topi|cap|hat|beanie|tas|bag|pack|duffel|sling|waist|pouch|wallet|dompet|belt|ikat pinggang/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Tas%')->orWhere('name', 'like', '%Aksesoris%')->value('id');
        } elseif (preg_match('/sepatu|shoe|boot|sandal|footwear/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Sepatu%')->orWhere('name', 'like', '%Alas Kaki%')->value('id');
        } elseif (preg_match('/wanita|women|dress|rok/i', $cat . ' ' . ($scrapedProduct['gender'] ?? ''))) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Wanita%')->value('id');
        } elseif (preg_match('/pria|men|kaos|shirt|t-shirt|kemeja|jaket|jacket|celana|pants|sweater/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Pria%')->value('id');
        } elseif (preg_match('/tenda|tent|sleeping|camp/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Camping%')->value('id');
        } elseif (preg_match('/mendaki|climb|carabiner|trekking/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Mendaki%')->value('id');
        } elseif (preg_match('/outdoor|cooking|botol|bottle|tumbler/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Outdoor%')->value('id');
        } elseif (preg_match('/elektronik|gadget|jam|watch|headlamp/i', $cat)) {
            $zoneId = \App\Models\Zone::where('name', 'like', '%Elektronik%')->value('id');
        }

        if (!$zoneId) {
            $zoneId = \App\Models\Zone::first()?->id;
        }
        $result['zone_id'] = $zoneId;

        if (empty($result['name'])) {
            return response()->json(['message' => 'Produk dengan kode ' . $code . ' tidak ditemukan di katalog PIM maupun CARE.'], 404);
        }

        return response()->json($result);
    }

    public function create()
    {
        $zones = Zone::all();
        $availableImages = [];
        return view('admin.products.create', compact('zones', 'availableImages'));
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
                        'image' => $var['image'] ?? null,
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
        $availableImages = $this->collectAvailableImages($product);
        return view('admin.products.edit', compact('product', 'zones', 'availableImages'));
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
                            'image' => $var['image'] ?? null,
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
     * Extract all unique images available for a product from PIM payloads, scraping data, and variants.
     */
    protected function collectAvailableImages(Product $product): array
    {
        $images = [];
        if (!empty($product->image)) {
            $images[] = $product->image;
        }

        // From pim_image_payload
        if (is_array($product->pim_image_payload)) {
            foreach ($product->pim_image_payload['generic'] ?? [] as $gen) {
                foreach ($gen['image'] ?? [] as $gi) {
                    if (!empty($gi['url'])) $images[] = $gi['url'];
                }
            }
            foreach ($product->pim_image_payload['variant'] ?? [] as $pvar) {
                foreach ($pvar['image'] ?? [] as $vi) {
                    if (!empty($vi['url'])) $images[] = $vi['url'];
                }
            }
        }

        // From pim_media
        if (is_array($product->pim_media)) {
            foreach ($product->pim_media as $m) {
                if (is_string($m) && !empty($m)) {
                    $images[] = $m;
                } elseif (is_array($m)) {
                    if (!empty($m['url'])) $images[] = $m['url'];
                    if (!empty($m['value'])) $images[] = $m['value'];
                }
            }
        }

        // From scraped products.json fallback
        $scraped = \App\Services\ProductEnrichmentService::findJsonProduct($product->sku);
        if (!empty($scraped['images'])) {
            foreach ($scraped['images'] as $img) {
                if (!empty($img['url'])) $images[] = $img['url'];
            }
        }

        // From existing variants
        foreach ($product->variants as $var) {
            if (!empty($var->image)) {
                $images[] = $var->image;
            }
        }

        return array_values(array_unique(array_filter($images)));
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
