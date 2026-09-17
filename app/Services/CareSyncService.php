<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RfidTag;
use App\Models\SyncLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CareSyncService
{
    public function __construct(private readonly CareOmniClient $care) {}

    /**
     * Get the active CARE base URL with auto-discovery fallback.
     */
    public function getBaseUrl(): string
    {
        return $this->care->masterUrl();
    }

    /**
     * Test connection to CARE API.
     *
     * @return array{online: bool, status_code: ?int, message: string, url: string, store_code: string}
     */
    public function testConnection(): array
    {
        $result = $this->care->testConnection();
        $result['url'] = $result['master_url'];
        $result['store_code'] = (string) config('services.care.store_code', '2022');

        return $result;
    }

    /**
     * Synchronize products from the official CARE OMNI staging/production APIs.
     *
     * @param  string  $source  e.g. 'api' | 'console' | 'scheduler' | 'web'
     * @return array{success: bool, message: string, source: string, status: string, synced_at: string}
     */
    public function sync(string $source = 'cli'): array
    {
        $startedAt = Carbon::now();

        try {
            $storeCode = config('services.care.store_code', '2022');
            $items = $this->care->allItems();

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
                if ($price !== null) {
                    $explicitParentPrices[$sku] = true;
                }
                $stock = isset($pItem['stock']) ? (int) $pItem['stock'] : null;

                $product = Product::where('sku', $sku)->first();

                if (! $product) {
                    $product = Product::create([
                        'sku' => $sku,
                        'name' => $name ?: ('SKU '.$sku),
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
                    $parentName = ! empty($vItem['name']) ? explode(' - ', $vItem['name'])[0] : ('SKU '.$parentSku);
                    $parent = Product::create([
                        'sku' => $parentSku,
                        'name' => $parentName,
                        'price' => isset($vItem['price']) ? (float) $vItem['price'] : 0,
                        'stock' => 0,
                    ]);
                    ProductEnrichmentService::enrichProduct($parent);
                    $createdCount++;
                } elseif ((float) $parent->price === 0.0 && isset($vItem['price'])) {
                    $parent->update(['price' => (float) $vItem['price']]);
                }

                // Extract color and size from name if available
                $vName = $vItem['name'] ?? ('Variant '.$vSku);
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

                $variant = ProductVariant::updateOrCreate(
                    ['sku' => $vSku],
                    [
                        'product_id' => $parent->id,
                        'name' => $vName,
                        'color' => $color,
                        'size' => $size,
                        'price' => $vPrice,
                        'stock' => $vStock,
                    ]
                );
                // CARE owns price and stock; PIM owns the image. A CARE variant may
                // arrive after its parent was enriched by PIM, so fill only empty
                // variant images from the stable parent cover.
                if (! $variant->image && $parent->image) {
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
                    RfidTag::where('product_id', $old->id)->update(['product_id' => $parent->id]);
                    DB::table('tablet_recommendations')->where('product_id', $old->id)->update(['product_id' => $parent->id]);
                    $old->delete();
                }
            }

            // 4. Update parent products total stock from variants
            foreach (Product::has('variants')->get() as $p) {
                $totalStock = $p->variants()->sum('stock');
                $updates = [];
                if ((int) $p->stock !== $totalStock) {
                    $updates['stock'] = $totalStock;
                }
                if (isset($variantPrices[$p->sku]) && ! isset($explicitParentPrices[$p->sku])
                    && (float) $p->price !== $variantPrices[$p->sku]) {
                    $updates['price'] = $variantPrices[$p->sku];
                }
                if ($updates) {
                    $p->update($updates);
                }
            }

            $status = 'success';
            $message = sprintf(
                'CARE sync completed (Store: %s). Total: %d main products (%d created, %d updated) and %d variants attached.',
                $storeCode,
                count($parentItems),
                $createdCount,
                $updatedCount,
                $variantCount
            );

            $log = SyncLog::create([
                'source' => 'care-'.$source,
                'status' => $status,
                'message' => $message,
                'synced_at' => $startedAt,
            ]);

            // Ensure all products have zone, material, and description filled
            ProductEnrichmentService::backfillAll();

            DB::commit();

            Log::info('CareSyncService: success', ['log_id' => $log->id, 'details' => $message]);

            return [
                'success' => true,
                'message' => $message,
                'source' => $log->source,
                'status' => $status,
                'synced_at' => $startedAt->toIso8601String(),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            $message = 'CARE sync failed: '.$e->getMessage();

            $log = SyncLog::create([
                'source' => 'care-'.$source,
                'status' => 'failed',
                'message' => $message,
                'synced_at' => $startedAt,
            ]);

            Log::error('CareSyncService: failed', [
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $message,
                'source' => $log->source,
                'status' => 'failed',
                'synced_at' => $startedAt->toIso8601String(),
            ];
        }
    }
}
