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
        config(['pim.url' => 'http://pim.test']);
        $existing = Product::factory()->create(['sku' => '910009029001', 'price' => 250000, 'stock' => 7]);
        $product = ['generic' => '910009029', 'name' => 'TOURER WANDER 1.1 22L 1A',
            'mainImage' => 'https://storage.eigeradventure.com/tourer.jpg', 'weight' => 725,
            'variant' => [['sku' => '910009029001', 'name' => 'TOURER WANDER - BLACK',
                'color' => 'BLACK', 'size' => '22L', 'moq' => '2', 'ecmsku' => 'ECM1',
                'customAttributes' => [['attributeCode' => 'fabric', 'value' => 'Canvas']]]],
            'media' => [['attributeCode' => 'SIZE_CHART', 'files' => [['value' => 'https://storage.eigeradventure.com/chart.jpg', 'description' => 'Chart']]]],
            'customAtributes' => [['attributeCode' => 'long_description', 'value' => 'Official description'],
                ['attributeCode' => 'gender', 'value' => 'Unisex']],
            'technology' => [['name' => 'Tech']], 'activity' => [['name' => 'Hiking']],
            'specification' => [['code' => 'PRODUCT_WEIGHT', 'value' => '725']]];
        $images = ['generic' => [['sku' => '910009029', 'image' => [['url' => 'https://storage.eigeradventure.com/tourer.jpg', 'type' => 'main_image']]]],
            'variant' => [['sku' => '910009029001', 'image' => [['url' => 'https://storage.eigeradventure.com/black.jpg', 'type' => 'main_image']]]]];
        Http::fake([
            '*/api/ui/articles*' => Http::response(['status' => true, 'data' => [['sap_id' => '910009029']],
                'pagination' => ['total_pages' => 1]], 200),
            '*/api/articles/910009029/product-payload' => Http::response($product, 200),
            '*/api/articles/910009029/image-payload' => Http::response($images, 200),
        ]);

        $response = $this->post(route('admin.pim.sync'));

        $response->assertRedirect(route('admin.pim.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => '910009029',
            'name' => 'TOURER WANDER 1.1 22L 1A',
        ]);
        $this->assertSame($product, Product::where('sku', '910009029')->first()->pim_payload);
        $this->assertSame(250000, (int) $existing->fresh()->price);
        $this->assertSame(7, $existing->fresh()->stock);
        $this->assertSame('ECM1', Product::where('sku', '910009029')->first()->variants()->first()->ecmsku);
        $this->assertSame('2', Product::where('sku', '910009029')->first()->variants()->first()->moq);
        $this->assertSame('Canvas', Product::where('sku', '910009029')->first()->variants()->first()->custom_attributes[0]['value']);
    }
}
