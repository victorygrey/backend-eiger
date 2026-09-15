<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_integrations_page_loads_successfully(): void
    {
        Http::fake([
            '*/api/health' => Http::response([
                'success' => true,
                'status' => 'online',
            ], 200),
            '*/api/articles/channel-list*' => Http::response([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'pagination' => ['total' => 60],
                ],
            ], 200),
        ]);

        $response = $this->get(route('admin.integrations.index'));

        $response->assertStatus(200);
        $response->assertSee('Integrasi Sistem PIM & CARE OMNI', false);
        $response->assertSee('Sinkronkan Keduanya Sekarang');
        $response->assertSee('1. Master Katalog PIM');
        $response->assertSee('2. Ritel & Stok CARE OMNI', false);
        $response->assertSee('Jadwal Auto-Sync');
        $response->assertSee('Tiap 10 Menit');
    }

    public function test_sync_all_triggers_both_pim_and_care(): void
    {
        Http::fake([
            '*/api/articles/publish-list*' => Http::response([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'data' => [
                        [
                            'sap_id' => '910009029',
                            'name' => 'TOURER WANDER 1.1 22L 1A',
                            'mc_name' => 'Tas',
                            'thumbnails_image' => 'http://192.168.18.31:8001/media/sample.jpg',
                        ],
                    ],
                    'pagination' => ['total' => 1],
                ],
            ], 200),
            '*/api/server/pricing_details*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910009029001',
                        'articleprice' => '250000.00',
                        'loccode' => '2022',
                    ],
                ],
            ], 200),
            '*/api/server/stocks*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910009029001',
                        'loccode' => '2022',
                        'stock' => 15,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('admin.integrations.sync-all'));

        $response->assertRedirect(route('admin.integrations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => '910009029',
            'name' => 'TOURER WANDER 1.1 22L 1A',
            'price' => 250000,
            'stock' => 15,
        ]);
        $this->assertNotEmpty(Product::where('sku', '910009029')->value('image'));

        $this->assertDatabaseHas('product_variants', [
            'sku' => '910009029001',
            'price' => 250000,
            'stock' => 15,
        ]);
    }

    public function test_individual_pim_and_care_sync_endpoints(): void
    {
        Http::fake([
            '*/api/articles/publish-list*' => Http::response([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'data' => [],
                    'pagination' => ['total' => 0],
                ],
            ], 200),
            '*/api/server/pricing_details*' => Http::response([
                'data' => [],
            ], 200),
            '*/api/server/stocks*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $responsePim = $this->post(route('admin.integrations.sync-pim'));
        $responsePim->assertRedirect(route('admin.integrations.index'));

        $responseCare = $this->post(route('admin.integrations.sync-care'));
        $responseCare->assertRedirect(route('admin.integrations.index'));
    }
}
