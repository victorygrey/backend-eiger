<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\AtomProductActivityGroup;
use App\Models\AtomProductCategory;
use App\Services\PimCareProductMapper;
use App\Services\AtomMasterDataResolver;
use App\Services\PimProductLookup;
use App\Services\ProductEnrichmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    /**
     * Display a paginated list of products with search and filtering.
     */
    public function index(Request $request)
    {
        $products = Product::with([
            'zone',
            'variants',
            'atomCategory',
            'atomSubCategory',
            'activitiesRelation.atomActivity.group',
        ])
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
            ->when($request->filled('category'), function ($query) use ($request) {
                $category = $request->string('category')->toString();
                $query->where(function ($categories) use ($category) {
                    $categories->where('category', $category)
                        ->orWhereHas('atomCategory', fn ($master) => $master->where('name', $category))
                        ->orWhereHas('atomSubCategory', fn ($master) => $master->where('name', $category));
                });
            })
            ->when($request->filled('activity'), function ($query) use ($request) {
                $activity = $request->string('activity')->toString();
                $query->whereHas('activitiesRelation', function ($activities) use ($activity) {
                    $activities->where('name', $activity)
                        ->orWhereHas('atomActivity.group', fn ($group) => $group->where('name', $activity));
                });
            })
            ->when($request->filled('ai_status'), fn ($query) => $query->where('ai_fit_and_go_active', $request->boolean('ai_status')))
            ->when($request->filled('tablet_status'), fn ($query) => $query->where('interactive_tablet_active', $request->boolean('tablet_status')))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = AtomProductCategory::query()
            ->with(['subCategories' => fn ($query) => $query->orderBy('sequence_number')->orderBy('name')])
            ->orderBy('sequence_number')->orderBy('name')->get();
        $activityGroups = AtomProductActivityGroup::query()
            ->with(['activities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('sequence_number')->orderBy('name')->get();
        $channelSettingsUnlocked = $this->channelSettingsUnlocked($request);

        return view('admin.products.index', compact('products', 'categories', 'activityGroups', 'channelSettingsUnlocked'));
    }

    /**
     * Lock or unlock channel switches for the current authenticated session.
     */
    public function updateChannelLock(Request $request)
    {
        $data = $request->validate(['unlocked' => ['required', 'boolean']]);
        $request->session()->put('products.channel_settings_unlocked', $data['unlocked']);

        return back()->with('success', $data['unlocked']
            ? 'Pengaturan AI Product dan Tablet berhasil dibuka.'
            : 'Pengaturan AI Product dan Tablet berhasil dikunci.');
    }

    /**
     * Update one Digital Store channel without changing the PIM/CARE product data.
     */
    public function updateChannel(Request $request, Product $product)
    {
        abort_unless(
            $this->channelSettingsUnlocked($request),
            423,
            'Pengaturan kanal masih terkunci.'
        );

        $data = $request->validate([
            'channel' => ['required', Rule::in(['ai_fit_and_go_active', 'interactive_tablet_active'])],
            'active' => ['required', 'boolean'],
        ]);

        $product->update([$data['channel'] => $data['active']]);

        $channelName = $data['channel'] === 'ai_fit_and_go_active' ? 'AI Fit & Go' : 'Interactive Tablet';

        return back()->with('success', sprintf(
            '%s berhasil %s untuk %s.',
            $product->name,
            $data['active'] ? 'diaktifkan' : 'dinonaktifkan',
            $channelName,
        ));
    }

    private function channelSettingsUnlocked(Request $request): bool
    {
        return filter_var(
            $request->session()->get('products.channel_settings_unlocked', false),
            FILTER_VALIDATE_BOOLEAN
        );
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
            'performances' => $product->performances,
            'specifications' => $product->specifications,
            'custom_attributes' => $product->custom_attributes_list,
            'weight' => $product->weight,
            'media' => $product->pim_media ?? [],
            'variants' => $product->variants->map->only(['sku', 'name', 'color', 'size', 'price', 'stock'])->all(),
            'detail_url' => route('admin.products.show', $product),
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

    /**
     * Display the product data received from PIM/CARE without exposing manual edits.
     */
    public function show(Product $product, AtomMasterDataResolver $masterDataResolver)
    {
        $product->load([
            'zone',
            'variants.attributesRelation',
            'pimRecord',
            'atomCategory',
            'atomSubCategory',
            'customAttributesRelation',
            'technologiesRelation',
            'activitiesRelation.atomActivity.group',
            'performancesRelation',
            'specificationsRelation',
            'mediaRelation.variant',
        ]);

        $availableImages = $this->collectAvailableImages($product);
        $customAttributes = collect($product->custom_attributes_list)->map(function (array $attribute) use ($masterDataResolver) {
            $attribute['master_values'] = $masterDataResolver->displayValues(
                isset($attribute['value']) && is_scalar($attribute['value']) ? (string) $attribute['value'] : null,
                (string) ($attribute['attributeCode'] ?? ''),
            );

            return $attribute;
        })->all();

        return view('admin.products.show', compact('product', 'availableImages', 'customAttributes'));
    }

    /**
     * Extract all unique images available for a product from PIM payloads, scraping data, and variants.
     */
    protected function collectAvailableImages(Product $product): array
    {
        $images = [];
        $storedPhotoCount = 0;
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
                if ($url !== '') {
                    $images[] = $url;
                    $storedPhotoCount++;
                }
            }
        }

        // Raw signed URLs are a fallback for records that predate local media
        // copies. Once local photos exist, using both would duplicate thumbnails.
        if ($storedPhotoCount === 0 && is_array($product->pim_image_payload)) {
            foreach ($product->pim_image_payload['generic'] ?? [] as $gen) {
                foreach ($gen['image'] ?? [] as $gi) {
                    if (! empty($gi['url'])) {
                        $images[] = $gi['url'];
                    }
                }
            }
            foreach ($product->pim_image_payload['variant'] ?? [] as $pvar) {
                foreach ($pvar['image'] ?? [] as $vi) {
                    if (! empty($vi['url'])) {
                        $images[] = $vi['url'];
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
     * Remove a product that is no longer needed in the Digital Store catalog.
     */
    public function destroy(Product $product)
    {
        $productName = $product->name;
        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', $productName.' berhasil dihapus dari katalog CMS.');
    }

}
