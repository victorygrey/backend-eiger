<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PimSyncService
{
    /**
     * Test HTTP connection to PIM Simulator.
     */
    public function testConnection(): array
    {
        $baseUrl = rtrim(config('pim.url', 'http://192.168.18.31:8001'), '/');
        $result = [
            'online' => false,
            'url' => $baseUrl,
            'article_count' => 0,
            'message' => 'Belum diperiksa',
        ];

        if (app()->runningUnitTests() && !config('pim.test_connection', false)) {
            return $result;
        }

        try {
            $resp = Http::timeout(3)->get($baseUrl . '/api/articles/channel-list');
            if ($resp->successful()) {
                $result['online'] = true;
                $result['message'] = 'PIM Server terhubung dan aktif.';
                try {
                    $countResp = Http::timeout(3)->get($baseUrl . '/api/articles/publish-list?limit=1');
                    if ($countResp->successful()) {
                        $result['article_count'] = (int) ($countResp->json('data.pagination.total') ?? 0);
                    }
                } catch (\Throwable $e) {
                    // ignore count failure
                }
            } else {
                $result['message'] = 'Server PIM merespons HTTP ' . $resp->status();
            }
        } catch (\Throwable $e) {
            $result['message'] = 'Koneksi ke PIM gagal: ' . $e->getMessage();
        }

        return $result;
    }

    /**
     * Synchronize master catalog products from PIM Simulator.
     */
    public function sync(string $source = 'web'): array
    {
        $startedAt = now();
        $baseUrl = rtrim(config('pim.url', 'http://192.168.18.31:8001'), '/');

        try {
            $response = Http::timeout(config('pim.timeout', 15))->get($baseUrl . '/api/articles/publish-list', [
                'limit' => 100,
            ]);

            if (! $response->successful()) {
                $msg = 'Gagal menghubungi PIM API (HTTP ' . $response->status() . ').';
                SyncLog::create([
                    'source' => 'pim-' . $source,
                    'status' => 'failed',
                    'message' => $msg,
                    'created_at' => $startedAt,
                ]);
                return ['success' => false, 'message' => $msg, 'count' => 0];
            }

            $articles = $response->json('data.data') ?? [];
            if (empty($articles)) {
                $msg = 'Tidak ada artikel ditemukan di katalog PIM.';
                return ['success' => true, 'message' => $msg, 'count' => 0];
            }

            $createdCount = 0;
            $updatedCount = 0;

            DB::beginTransaction();

            foreach ($articles as $art) {
                $sku = (string) ($art['sap_id'] ?? '');
                if (! $sku) continue;

                $name = $art['name'] ?? ('SKU ' . $sku);
                $image = $art['thumbnails_image'] ?? null;

                $product = Product::where('sku', $sku)->first();

                if (! $product) {
                    Product::create([
                        'sku' => $sku,
                        'name' => $name,
                        'image' => $image,
                        'price' => 0,
                        'stock' => 0,
                    ]);
                    $createdCount++;
                } else {
                    $updates = [];
                    if ($product->name !== $name && ! empty($name)) {
                        $updates['name'] = $name;
                    }
                    if (! empty($image) && empty($product->image)) {
                        $updates['image'] = $image;
                    }
                    if (! empty($updates)) {
                        $product->update($updates);
                        $updatedCount++;
                    }
                }
            }

            DB::commit();

            $msg = sprintf('Katalog PIM berhasil disinkronkan: %d artikel diproses (%d baru, %d diperbarui).', count($articles), $createdCount, $updatedCount);

            SyncLog::create([
                'source' => 'pim-' . $source,
                'status' => 'success',
                'message' => $msg,
                'created_at' => $startedAt,
            ]);

            return [
                'success' => true,
                'message' => $msg,
                'count' => count($articles),
                'created' => $createdCount,
                'updated' => $updatedCount,
            ];

        } catch (\Throwable $e) {
            DB::rollBack();

            $msg = 'Sinkronisasi PIM gagal: ' . $e->getMessage();
            SyncLog::create([
                'source' => 'pim-' . $source,
                'status' => 'failed',
                'message' => $msg,
                'created_at' => $startedAt,
            ]);

            return ['success' => false, 'message' => $msg, 'count' => 0];
        }
    }
}
