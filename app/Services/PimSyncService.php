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
        return app(PimCatalogSync::class)->run($source);
    }
}
