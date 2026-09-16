<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareSyncService
{
    /**
     * Get the active CARE base URL with auto-discovery fallback.
     */
    public function getBaseUrl(): string
    {
        $configured = rtrim((string) config('services.care.url', 'http://192.168.18.31:8002'), '/');

        // 1. If configured URL responds healthy, use it
        try {
            $resp = Http::timeout(2)->acceptJson()->get($configured . '/api/health');
            if ($resp->successful()) {
                return $configured;
            }
        } catch (\Throwable $e) {
            // Probe fallback candidates below
        }

        // 2. Candidates: TrueNAS host endpoint, then localhost
        $candidates = [
            'http://192.168.18.31:8002',
            'http://127.0.0.1:8002',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === $configured) {
                continue;
            }
            try {
                $resp = Http::timeout(2)->acceptJson()->get($candidate . '/api/health');
                if ($resp->successful()) {
                    return $candidate;
                }
            } catch (\Throwable $e) {
                // Try next
            }
        }

        return $configured;
    }

    /**
     * Test connection to CARE API.
     *
     * @return array{online: bool, status_code: ?int, message: string, url: string, store_code: string}
     */
    public function testConnection(): array
    {
        $baseUrl = $this->getBaseUrl();
        $storeCode = config('services.care.store_code', '2022');
        $serverKey = config('services.care.server_key');
        $timeout = (int) config('services.care.timeout', 5);

        try {
            // First check health
            $healthUrl = rtrim($baseUrl, '/') . '/api/health';
            $healthResp = Http::timeout($timeout)->acceptJson()->get($healthUrl);

            if ($healthResp->successful()) {
                return [
                    'online' => true,
                    'status_code' => $healthResp->status(),
                    'message' => 'CARE Server connected successfully.',
                    'url' => $baseUrl,
                    'store_code' => $storeCode,
                ];
            }

            // If no health route, test pricing details with server key
            $pricingUrl = rtrim($baseUrl, '/') . '/api/server/pricing_details';
            $headers = $serverKey ? ['x-server-key' => $serverKey] : [];
            $pricingResp = Http::timeout($timeout)->withHeaders($headers)->acceptJson()->get($pricingUrl);

            if ($pricingResp->successful()) {
                return [
                    'online' => true,
                    'status_code' => $pricingResp->status(),
                    'message' => 'CARE Server connected successfully.',
                    'url' => $baseUrl,
                    'store_code' => $storeCode,
                ];
            }

            return [
                'online' => false,
                'status_code' => $pricingResp->status(),
                'message' => 'CARE Server responded with HTTP ' . $pricingResp->status(),
                'url' => $baseUrl,
                'store_code' => $storeCode,
            ];
        } catch (\Throwable $e) {
            return [
                'online' => false,
                'status_code' => null,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'url' => $baseUrl,
                'store_code' => $storeCode,
            ];
        }
    }

    /**
     * Synchronize products from CARE Simulator API.
     *
     * Supports both modern CARE OMNI endpoints (September 2026 spec)
     * and fallback to legacy dummy endpoints.
     *
     * @param  string  $source  e.g. 'api' | 'console' | 'scheduler' | 'web'
     * @return array{success: bool, message: string, source: string, status: string, synced_at: string}
     */
    public function sync(string $source = 'cli'): array
    {
        $startedAt = Carbon::now();

        try {
            $baseUrl = $this->getBaseUrl();
            $serverKey = config('services.care.server_key');
            $storeCode = config('services.care.store_code', '2022');
            $timeout = (int) config('services.care.timeout', 10);

            // Attempt 1: CARE OMNI (September 2026) Official Schema
            $items = $this->fetchFromCareOmni($baseUrl, $serverKey, $storeCode, $timeout);

            // Attempt 2: Fallback to legacy /api/products if CARE OMNI not available
            if ($items === null) {
                $items = $this->fetchFromLegacyApi($baseUrl, $timeout);
            }

            $createdCount = 0;
            $updatedCount = 0;
            $unchangedCount = 0;

            DB::beginTransaction();

            // Separate items into Parent Articles (9-digit or standalone) and Variants (12-digit)
            $parentItems = [];
            $variantItems = [];
            $explicitParentPrices = [];
            $variantPrices = [];
            $careVariantSkusByParent = [];

            foreach ($items as $item) {
                $sku = (string) ($item['sku'] ?? '');
                if (! $sku) {
                    continue;
                }
                if (strlen($sku) === 12 && ctype_digit($sku)) {
                    $variantItems[] = $item;
                } else {
                    $parentItems[] = $item;
                }
            }

            // 1. Process Parent Products (Main Articles)
            foreach ($parentItems as $pItem) {
                $sku = $pItem['sku'];
                $name = $pItem['name'] ?? null;
                $price = isset($pItem['price']) ? (float) $pItem['price'] : null;
                if ($price !== null) $explicitParentPrices[$sku] = true;
                $stock = isset($pItem['stock']) ? (int) $pItem['stock'] : null;

                $product = Product::where('sku', $sku)->first();

                if (! $product) {
                    $product = Product::create([
                        'sku'   => $sku,
                        'name'  => $name ?: ('SKU ' . $sku),
                        'price' => $price ?? 0,
                        'stock' => $stock ?? 0,
                    ]);
                    ProductEnrichmentService::enrichProduct($product);
                    $createdCount++;
                } else {
                    $hasChanges = false;
                    $updateData = [];

                    // Always update name if item provides a real name
                    if (! empty($name) && $product->name !== $name) {
                        $updateData['name'] = $name;
                        $hasChanges = true;
                    }
                    if ($price !== null && (float) $product->price !== $price) {
                        $updateData['price'] = $price;
                        $hasChanges = true;
                    }
                    if ($stock !== null && (int) $product->stock !== $stock) {
                        $updateData['stock'] = $stock;
                        $hasChanges = true;
                    }

                    if ($hasChanges) {
                        $product->update($updateData);
                        $updatedCount++;
                    } else {
                        $unchangedCount++;
                    }
                }
            }

            // 2. Process Variants and attach to Parent Products
            $variantCount = 0;
            foreach ($variantItems as $vItem) {
                $vSku = $vItem['sku'];
                $parentSku = substr($vSku, 0, 9);
                $careVariantSkusByParent[$parentSku][] = $vSku;
                $parent = Product::where('sku', $parentSku)->first();

                if (! $parent) {
                    $parentName = !empty($vItem['name']) ? explode(' - ', $vItem['name'])[0] : ('SKU ' . $parentSku);
                    $parent = Product::create([
                        'sku'   => $parentSku,
                        'name'  => $parentName,
                        'price' => isset($vItem['price']) ? (float) $vItem['price'] : 0,
                        'stock' => 0,
                    ]);
                    ProductEnrichmentService::enrichProduct($parent);
                    $createdCount++;
                } elseif ((float) $parent->price === 0.0 && isset($vItem['price'])) {
                    $parent->update(['price' => (float) $vItem['price']]);
                }

                // Extract color and size from name if available
                $vName = $vItem['name'] ?? ('Variant ' . $vSku);
                $color = null;
                $size = null;
                if (str_contains($vName, ' - ')) {
                    $parts = explode(' - ', $vName);
                    if (count($parts) >= 3) {
                        $color = trim($parts[1]);
                        $size = trim($parts[2]);
                    }
                }

                $vPrice = isset($vItem['price']) ? (float) $vItem['price'] : (float) $parent->price;
                if (isset($vItem['price'])) {
                    $variantPrices[$parentSku] = isset($variantPrices[$parentSku])
                        ? min($variantPrices[$parentSku], $vPrice) : $vPrice;
                }
                $vStock = isset($vItem['stock']) ? (int) $vItem['stock'] : 0;

                $variant = \App\Models\ProductVariant::updateOrCreate(
                    ['sku' => $vSku],
                    [
                        'product_id' => $parent->id,
                        'name'       => $vName,
                        'color'      => $color,
                        'size'       => $size,
                        'price'      => $vPrice,
                        'stock'      => $vStock,
                    ]
                );
                // CARE owns price and stock; PIM owns the image. A CARE variant may
                // arrive after its parent was enriched by PIM, so fill only empty
                // variant images from the stable parent cover.
                if (!$variant->image && $parent->image) {
                    $variant->update(['image' => $parent->image]);
                }
                $variantCount++;
            }

            // CARE is authoritative for sellable variants. Remove obsolete PIM
            // article-level rows (for example a 9-digit SKU with comma-separated
            // sizes) and variants that no longer exist in CARE.
            foreach ($careVariantSkusByParent as $parentSku => $careVariantSkus) {
                $parent = Product::where('sku', $parentSku)->first();
                $parent?->variants()->whereNotIn('sku', $careVariantSkus)->delete();
            }

            // 3. Clean up any 12-digit variant items previously left directly in the products table
            $old12Products = Product::whereRaw('length(sku) = 12')->get();
            foreach ($old12Products as $old) {
                $pSku = substr($old->sku, 0, 9);
                $parent = Product::where('sku', $pSku)->first();
                if ($parent) {
                    \App\Models\RfidTag::where('product_id', $old->id)->update(['product_id' => $parent->id]);
                    DB::table('tablet_recommendations')->where('product_id', $old->id)->update(['product_id' => $parent->id]);
                    $old->delete();
                }
            }

            // 4. Update parent products total stock from variants
            foreach (Product::has('variants')->get() as $p) {
                $totalStock = $p->variants()->sum('stock');
                $updates = [];
                if ((int) $p->stock !== $totalStock) $updates['stock'] = $totalStock;
                if (isset($variantPrices[$p->sku]) && !isset($explicitParentPrices[$p->sku])
                    && (float) $p->price !== $variantPrices[$p->sku]) {
                    $updates['price'] = $variantPrices[$p->sku];
                }
                if ($updates) $p->update($updates);
            }

            $status  = 'success';
            $message = sprintf(
                'CARE sync completed (Store: %s). Total: %d main products (%d created, %d updated) and %d variants attached.',
                $storeCode,
                count($parentItems),
                $createdCount,
                $updatedCount,
                $variantCount
            );

            $log = SyncLog::create([
                'source'    => 'care-' . $source,
                'status'    => $status,
                'message'   => $message,
                'synced_at' => $startedAt,
            ]);

            // Ensure all products have zone, material, and description filled
            ProductEnrichmentService::backfillAll();

            DB::commit();

            Log::info('CareSyncService: success', ['log_id' => $log->id, 'details' => $message]);

            return [
                'success'   => true,
                'message'   => $message,
                'source'    => $log->source,
                'status'    => $status,
                'synced_at' => $startedAt->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            $message = 'CARE sync failed: ' . $e->getMessage();

            $log = SyncLog::create([
                'source'    => 'care-' . $source,
                'status'    => 'failed',
                'message'   => $message,
                'synced_at' => $startedAt,
            ]);

            Log::error('CareSyncService: failed', [
                'log_id' => $log->id,
                'error'  => $e->getMessage(),
            ]);

            return [
                'success'   => false,
                'message'   => $message,
                'source'    => $log->source,
                'status'    => 'failed',
                'synced_at' => $startedAt->toIso8601String(),
            ];
        }
    }

    /**
     * Fetch prices and stocks from official CARE OMNI endpoints.
     *
     * @return array<int, array{sku: string, price: ?float, stock: ?int, name: ?string}>|null
     */
    protected function fetchFromCareOmni(string $baseUrl, ?string $serverKey, string $storeCode, int $timeout): ?array
    {
        try {
            $headers = ['Accept' => 'application/json'];
            if ($serverKey) {
                $headers['x-server-key'] = $serverKey;
            }

            // 1. Fetch Pricing Details for Store
            $pricingUrl = rtrim($baseUrl, '/') . '/api/server/pricing_details';
            $pricingResp = Http::timeout($timeout)
                ->withHeaders($headers)
                ->acceptJson()
                ->get($pricingUrl, ['filter' => ['loccode' => $storeCode]]);

            if (! $pricingResp->successful()) {
                return null;
            }

            $pricingData = $pricingResp->json('data') ?? [];
            if (empty($pricingData) && ! is_array($pricingData)) {
                return null;
            }

            // Map pricing by skucode
            $itemsMap = [];
            foreach ($pricingData as $p) {
                $sku = $p['skucode'] ?? null;
                if (! $sku) {
                    continue;
                }
                $itemsMap[$sku] = [
                    'sku' => (string) $sku,
                    'price' => isset($p['articleprice']) ? (float) $p['articleprice'] : null,
                    'stock' => 0,
                    'name' => null,
                ];
            }

            // 2. Fetch Stocks for Store
            $stockUrl = rtrim($baseUrl, '/') . '/api/server/stocks';
            $stockResp = Http::timeout($timeout)
                ->withHeaders($headers)
                ->acceptJson()
                ->get($stockUrl, ['filter' => ['loccode' => $storeCode]]);

            if ($stockResp->successful()) {
                $stockData = $stockResp->json('data') ?? [];
                foreach ($stockData as $s) {
                    $sku = $s['skucode'] ?? null;
                    if (! $sku) {
                        continue;
                    }
                    if (! isset($itemsMap[$sku])) {
                        $itemsMap[$sku] = [
                            'sku' => (string) $sku,
                            'price' => null,
                            'stock' => 0,
                            'name' => null,
                        ];
                    }
                    $itemsMap[$sku]['stock'] = isset($s['stock']) ? (int) $s['stock'] : 0;
                }
            }

            // 3. Fetch Master Product Catalog from CARE /api/products for Names and fallback Stocks
            $catalogNames = [];
            $catalogStocks = [];
            try {
                $productsUrl = rtrim($baseUrl, '/') . '/api/products';
                $prodResp = Http::timeout($timeout)->acceptJson()->get($productsUrl);
                if ($prodResp->successful()) {
                    $prodData = $prodResp->json('data') ?? [];
                    foreach ($prodData as $pr) {
                        $pSku = $pr['sku'] ?? null;
                        if (! $pSku) {
                            continue;
                        }
                        if (! empty($pr['name'])) {
                            $catalogNames[(string) $pSku] = (string) $pr['name'];
                        }
                        if (isset($pr['stock'])) {
                            $catalogStocks[(string) $pSku] = (int) $pr['stock'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('CareSyncService: failed to fetch /api/products catalog', ['error' => $e->getMessage()]);
            }

            // Secondary fallback: local scrap-eiger products.json if available
            $scrapJsonPath = 'd:/LAPTOP FAIZAL/_Project/scrap-eiger/data/products.json';
            if (file_exists($scrapJsonPath)) {
                try {
                    $rawScraped = json_decode(file_get_contents($scrapJsonPath), true) ?: [];
                    foreach ($rawScraped as $scraped) {
                        $sSku9 = (string) ($scraped['product_code'] ?? $scraped['sku'] ?? '');
                        if (strlen($sSku9) === 9 && ! empty($scraped['product_name'])) {
                            if (! isset($catalogNames[$sSku9])) {
                                $catalogNames[$sSku9] = (string) $scraped['product_name'];
                            }
                            $colors = ! empty($scraped['available_colors']) ? $scraped['available_colors'] : ['STD'];
                            $sizes = ! empty($scraped['available_sizes']) ? $scraped['available_sizes'] : ['ALL'];
                            $vSeq = 1;
                            foreach ($colors as $c) {
                                foreach ($sizes as $sz) {
                                    $vSku12 = sprintf('%s%03d', $sSku9, $vSeq);
                                    if (! isset($catalogNames[$vSku12])) {
                                        $catalogNames[$vSku12] = sprintf('%s - %s - %s', $scraped['product_name'], $c, $sz);
                                    }
                                    $vSeq++;
                                }
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            // Assign catalog names and stocks to itemsMap
            foreach ($itemsMap as $sku => &$item) {
                if (isset($catalogNames[$sku])) {
                    $item['name'] = $catalogNames[$sku];
                }
                if ($item['stock'] === 0 && isset($catalogStocks[$sku]) && $catalogStocks[$sku] > 0) {
                    $item['stock'] = $catalogStocks[$sku];
                }
            }
            unset($item);

            return array_values($itemsMap);
        } catch (\Throwable $e) {
            Log::warning('fetchFromCareOmni error, attempting fallback', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Fallback to legacy /api/products endpoint.
     *
     * @return array<int, array{sku: string, price: ?float, stock: ?int, name: ?string}>
     */
    protected function fetchFromLegacyApi(string $baseUrl, int $timeout): array
    {
        $url = rtrim($baseUrl, '/') . '/api/products';
        $response = Http::timeout($timeout)->acceptJson()->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException('CARE API error with status code ' . $response->status());
        }

        $json = $response->json();
        return $json['data'] ?? [];
    }
}
