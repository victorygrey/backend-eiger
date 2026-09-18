<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use App\Models\Zone;
use App\Services\PimCareProductMapper;
use App\Services\PimFormData;
use App\Services\PimProductDataStore;
use App\Services\PimProductLookup;
use App\Services\ProductEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a paginated list of products with search and filtering.
     */
    public function index(Request $request)
    {
        $products = Product::with(['zone', 'variants'])
            ->withCount('variants')
            ->whereRaw('LENGTH(sku) = 9')
            ->where(function ($query) {
                $query->whereDoesntHave('pimRecord')->orWhere('pim_catalog_active', true);
            })
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
            ->when($request->filled('zone_id'), fn ($q) => $q->where('zone_id', $request->zone_id))
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
        try {
            return response()->json(app(PimProductLookup::class)->get($data['code']));
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['message' => 'PIM tidak dapat dihubungi atau payload belum tersedia.'], 502);
        }
    }

    public function mappingPreview(Product $product)
    {
        $product->load(['zone', 'variants']);

        return response()->json([
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'image' => $product->image,
            'images' => $this->collectAvailableImages($product),
            'price' => (float) $product->price,
            'stock' => $product->stock,
            'category' => $product->category,
            'zone' => $product->zone?->name,
            'description' => $product->description,
            'material' => $product->material,
            'technologies' => $product->technologies,
            'activities' => $product->activities,
            'specifications' => $product->specifications,
            'custom_attributes' => $product->custom_attributes_list,
            'weight' => $product->weight,
            'media' => $product->pim_media ?? [],
            'variants' => $product->variants->map->only(['sku', 'name', 'color', 'size', 'price', 'stock'])->all(),
            'edit_url' => route('admin.products.edit', $product),
        ]);
    }

    /**
     * Unified Catalog Lookup from PIM, CARE, and Scraping data.
     */
    public function catalogLookup(Request $request, PimCareProductMapper $mapper)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/']]);
        $code = trim($data['code']);
        $scraped = ProductEnrichmentService::findJsonProduct($code);
        $product = [
            'generic' => $code,
            'name' => $scraped['product_name'] ?? '',
            'mainImage' => $scraped['images'][0]['url'] ?? '',
            'variant' => [],
            'customAtributes' => array_values(array_filter([
                ! empty($scraped['description']) ? ['attributeCode' => 'long_description', 'value' => $scraped['description']] : null,
                ! empty($scraped['category']) ? ['attributeCode' => 'category', 'value' => $scraped['category']] : null,
                ! empty($scraped['gender']) ? ['attributeCode' => 'gender', 'value' => $scraped['gender']] : null,
            ])),
        ];
        $imagePayload = [];

        try {
            $pim = app(PimProductLookup::class)->get($code);
            if (! empty($pim['product'])) {
                $product = $pim['product'];
                $imagePayload = $pim['image'] ?? [];
            }
        } catch (\Throwable) {
            // Scraped data remains a read-only fallback when PIM is temporarily unavailable.
        }

        if (empty($product['name'])) {
            return response()->json([
                'message' => 'Produk dengan kode '.$code.' tidak ditemukan di katalog PIM maupun CARE.',
            ], 404);
        }

        $result = $mapper->map($product, $imagePayload, $scraped);
        $result['technology'] = $product['technology'] ?? [];
        $result['activity'] = $product['activity'] ?? [];
        $result['specification'] = $product['specification'] ?? [];
        $result['customAtributes'] = $product['customAtributes'] ?? [];
        $result['weight'] = (int) ($product['weight'] ?? 0);

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
        $data = app(PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        $variants = $data['variants'] ?? [];
        $pimSync = $data['_pim_sync'] ?? null;
        unset($data['variants'], $data['_pim_sync']);

        DB::transaction(function () use ($data, $variants, $pimSync) {
            $product = Product::create($data);
            foreach ($variants as $var) {
                $product->variants()->create([
                    'sku' => $var['sku'],
                    'name' => ($var['name'] ?? null) ?: ($product->name.' - '.($var['color'] ?? '').' - '.($var['size'] ?? '')),
                    'color' => $var['color'] ?? null,
                    'size' => $var['size'] ?? null,
                    'price' => $var['price'] ?? $product->price,
                    'stock' => $var['stock'] ?? 0,
                    'image' => ($var['image'] ?? null) ?: $product->image,
                ]);
            }
            if ($variants) {
                $product->update(['stock' => $product->variants()->sum('stock')]);
            }
            if ($pimSync) {
                app(PimProductDataStore::class)->replace(
                    $product, $pimSync['product'], $pimSync['image'], $pimSync['media'], source: 'admin-form'
                );
            }
        });

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
        $data = app(PimFormData::class)->apply($request->validated(), $request);
        $data['is_featured'] = $request->has('is_featured');
        $data['is_discontinued'] = $request->has('is_discontinued');

        $variants = $data['variants'] ?? ($request->boolean('variants_submitted') ? [] : null);
        $pimSync = $data['_pim_sync'] ?? null;
        unset($data['variants'], $data['_pim_sync']);
        DB::transaction(function () use ($product, $data, $variants, $pimSync) {
            $product->update($data);
            if ($variants === null) {
                if ($pimSync) {
                    app(PimProductDataStore::class)->replace(
                        $product, $pimSync['product'], $pimSync['image'], $pimSync['media'], source: 'admin-form'
                    );
                }

                return;
            }

            $existingSku = [];
            foreach ($variants as $var) {
                $existingSku[] = $var['sku'];
                $existingImage = $product->variants()->where('sku', $var['sku'])->value('image');
                $product->variants()->updateOrCreate(
                    ['sku' => $var['sku']],
                    [
                        'name' => ($var['name'] ?? null) ?: ($product->name.' - '.($var['color'] ?? '').' - '.($var['size'] ?? '')),
                        'color' => $var['color'] ?? null,
                        'size' => $var['size'] ?? null,
                        'price' => $var['price'] ?? $product->price,
                        'stock' => $var['stock'] ?? 0,
                        'image' => ($var['image'] ?? null) ?: ($existingImage ?: $product->image),
                    ]
                );
            }
            if ($existingSku) {
                $product->variants()->whereNotIn('sku', $existingSku)->delete();
            } else {
                $product->variants()->delete();
            }
            $product->update(['stock' => $product->variants()->sum('stock')]);
            if ($pimSync) {
                app(PimProductDataStore::class)->replace(
                    $product, $pimSync['product'], $pimSync['image'], $pimSync['media'], source: 'admin-form'
                );
            }
        });

        return redirect()->route('admin.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    /**
     * Extract all unique images available for a product from PIM payloads, scraping data, and variants.
     */
    protected function collectAvailableImages(Product $product): array
    {
        $images = [];
        $sourceToLocal = [];
        if (! empty($product->image)) {
            $images[] = $product->image;
        }

        // Prefer the durable CMS copy for product photos. Supplemental assets
        // (technology, size chart, and video) have their own preview section.
        if (is_array($product->pim_media)) {
            foreach ($product->pim_media as $media) {
                if (! is_array($media)) {
                    continue;
                }
                $role = strtolower((string) ($media['role'] ?? $media['type'] ?? 'gallery'));
                if (! in_array($role, ['main_image', 'gallery'], true)) {
                    continue;
                }
                $url = (string) ($media['url'] ?? $media['value'] ?? '');
                $sourceUrl = (string) ($media['source_url'] ?? '');
                if ($url !== '') {
                    $images[] = $url;
                    if ($sourceUrl !== '') {
                        $sourceToLocal[$sourceUrl] = $url;
                    }
                }
            }
        }

        // From pim_image_payload
        if (is_array($product->pim_image_payload)) {
            foreach ($product->pim_image_payload['generic'] ?? [] as $gen) {
                foreach ($gen['image'] ?? [] as $gi) {
                    if (! empty($gi['url'])) {
                        $images[] = $sourceToLocal[$gi['url']] ?? $gi['url'];
                    }
                }
            }
            foreach ($product->pim_image_payload['variant'] ?? [] as $pvar) {
                foreach ($pvar['image'] ?? [] as $vi) {
                    if (! empty($vi['url'])) {
                        $images[] = $sourceToLocal[$vi['url']] ?? $vi['url'];
                    }
                }
            }
        }

        // From scraped products.json fallback
        if (count($images) <= 1) {
            $scraped = ProductEnrichmentService::findJsonProduct($product->sku);
            if (! empty($scraped['images'])) {
                foreach ($scraped['images'] as $img) {
                    if (! empty($img['url'])) {
                        $images[] = $img['url'];
                    }
                }
            }
        }

        // From existing variants
        foreach ($product->variants as $var) {
            if (! empty($var->image)) {
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
