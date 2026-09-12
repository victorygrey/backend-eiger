<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CareIntegrationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_index_page_loads_successfully(): void
    {
        Http::fake([
            '*/api/health' => Http::response([
                'success' => true,
                'status' => 'online',
            ], 200),
        ]);

        $response = $this->get(route('admin.care.index'));

        $response->assertStatus(200);
        $response->assertSee('Integrasi Harga & Stok CARE OMNI', false);
        $response->assertSee('Toko Aktif (Fase 1)');
        $response->assertSee('Setiabudi');
        $response->assertSee('2022');
    }

    public function test_care_web_sync_triggers_and_redirects(): void
    {
        Http::fake([
            '*/api/server/pricing_details*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910012408001',
                        'articleprice' => '175000.00',
                        'loccode' => '2022',
                    ],
                ],
            ], 200),
            '*/api/server/stocks*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910012408001',
                        'loccode' => '2022',
                        'stock' => 25,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('admin.care.sync'));

        $response->assertRedirect(route('admin.care.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => '910012408',
            'price' => 175000,
            'stock' => 25,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => '910012408001',
            'price' => 175000,
            'stock' => 25,
        ]);
    }

    public function test_api_care_sync_endpoint_returns_json(): void
    {
        Http::fake([
            '*/api/server/pricing_details*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910012408002',
                        'articleprice' => '199000.00',
                        'loccode' => '2022',
                    ],
                ],
            ], 200),
            '*/api/server/stocks*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910012408002',
                        'loccode' => '2022',
                        'stock' => 15,
                    ],
                ],
            ], 200),
        ]);

        $response = $this->postJson('/api/sync/care');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('products', [
            'sku' => '910012408',
            'price' => 199000,
            'stock' => 15,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => '910012408002',
            'price' => 199000,
            'stock' => 15,
        ]);
    }
}
