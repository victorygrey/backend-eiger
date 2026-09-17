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
            '*/api/server/pricing_details*' => Http::response(['data' => []], 200),
            '*/api/server/inventories/bybin*' => Http::response(['data' => []], 200),
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
        $product = Product::factory()->create([
            'sku' => '910012408',
            'image' => '/api/pim-media/cover.jpg',
        ]);
        $product->variants()->create([
            'sku' => '910012408',
            'name' => 'Obsolete article-level variant',
            'size' => 'S, M, L',
        ]);
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
            '*/api/server/inventories/bybin*' => Http::response([
                'data' => [
                    [
                        'sku_code' => '910012408001',
                        'sku_generic_code' => '910012408',
                        'sku_name' => 'ACROSS - BLACK - M',
                        'available_qty' => 25,
                        'bin_status' => 'active',
                        'bin_category' => 'saleable goods',
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
            'image' => '/api/pim-media/cover.jpg',
        ]);
        $this->assertDatabaseMissing('product_variants', ['sku' => '910012408']);
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
            '*/api/server/inventories/bybin*' => Http::response([
                'data' => [
                    [
                        'sku_code' => '910012408002',
                        'sku_generic_code' => '910012408',
                        'sku_name' => 'ACROSS - BLACK - L',
                        'available_qty' => 15,
                        'bin_status' => 'active',
                        'bin_category' => 'saleable goods',
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

    public function test_care_sync_never_falls_back_to_the_local_simulator(): void
    {
        Http::fake([
            '*/api/server/pricing_details*' => Http::response(['message' => 'Unavailable'], 503),
            '*/api/products*' => Http::response([
                'data' => [[
                    'sku' => '910099999',
                    'name' => 'Simulator product',
                    'price' => 100000,
                    'stock' => 10,
                ]],
            ], 200),
        ]);

        $response = $this->post(route('admin.care.sync'));

        $response->assertRedirect(route('admin.care.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('products', ['sku' => '910099999']);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/api/products'));
    }
}
