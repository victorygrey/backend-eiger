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
     * Synchronize products from CARE Simulator API.
     *
     * Only updates products if their data (name, price, stock) has changed.
     *
     * @param  string  $source  e.g. 'api' | 'console' | 'scheduler'
     * @return array{success: bool, message: string, source: string, status: string, synced_at: string}
     */
    public function sync(string $source = 'cli'): array
    {
        $startedAt = Carbon::now();

        try {
            $baseUrl = config('services.care.url', 'http://127.0.0.1:8001');
            $url = rtrim($baseUrl, '/') . '/api/products';

            $response = Http::timeout(10)->acceptJson()->get($url);

            if (! $response->successful()) {
                throw new \RuntimeException('CARE API error with status code ' . $response->status());
            }

            $json = $response->json();
            $items = $json['data'] ?? [];

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
                        'name'  => $name,
                        'price' => $price,
                        'stock' => $stock,
                    ]);
                    $createdCount++;
                } else {
                    $hasChanges = false;
                    $updateData = [];

                    if ($name !== null && $product->name !== $name) {
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
                'CARE sync completed. Total: %d products (%d created, %d updated, %d unchanged).',
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
}
