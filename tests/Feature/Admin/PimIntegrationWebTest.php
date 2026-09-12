<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PimIntegrationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_pim_index_page_loads_successfully(): void
    {
        $response = $this->get(route('admin.pim.index'));

        $response->assertStatus(200);
        $response->assertSee('Integrasi Produk PIM');
        $response->assertSee('Sinkronkan dari PIM');
    }

    public function test_pim_web_sync_triggers_and_redirects(): void
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
        ]);

        $response = $this->post(route('admin.pim.sync'));

        $response->assertRedirect(route('admin.pim.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => '910009029',
            'name' => 'TOURER WANDER 1.1 22L 1A',
            'image' => 'http://192.168.18.31:8001/media/sample.jpg',
        ]);
    }
}
