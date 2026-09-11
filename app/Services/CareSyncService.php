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
     * Test connection to CARE API.
     *
     * @return array{online: bool, status_code: ?int, message: string, url: string, store_code: string}
     */
    public function testConnection(): array
    {
        $baseUrl = config('services.care.url', 'http://127.0.0.1:8002');
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
            $baseUrl = config('services.care.url', 'http://127.0.0.1:8002');
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

            foreach ($items as $item) {
                $sku = $item['sku'] ?? null;
                if (! $sku) {
                    continue;
                }

                $name = $item['name'] ?? null;
                $price = isset($item['price']) ? (float) $item['price'] : null;
                $stock = isset($item['stock']) ? (int) $item['stock'] : null;

                $product = Product::where('sku', $sku)->first();

                if (! $product) {
                    Product::create([
                        'sku'   => $sku,
                        'name'  => $name ?? ('SKU ' . $sku),
                        'price' => $price,
                        'stock' => $stock ?? 0,
                    ]);
                    $createdCount++;
                } else {
                    $hasChanges = false;
                    $updateData = [];

                    // Only update name if product has no name yet and item provides one
                    if ($name !== null && (empty($product->name) || str_starts_with($product->name, 'SKU '))) {
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

            $status  = 'success';
            $message = sprintf(
                'CARE sync completed (Store: %s). Total: %d products (%d created, %d updated, %d unchanged).',
                $storeCode,
                count($items),
                $createdCount,
                $updatedCount,
                $unchangedCount
            );

            $log = SyncLog::create([
                'source'    => 'care-' . $source,
                'status'    => $status,
                'message'   => $message,
                'synced_at' => $startedAt,
            ]);

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
