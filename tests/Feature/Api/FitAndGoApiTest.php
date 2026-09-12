<?php

namespace Tests\Feature\Api;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitAndGoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed test device
        FitAndGoDevice::create([
            'name'          => 'Kiosk 01',
            'device_code'   => 'fit-kiosk-01',
            'location'      => 'Lantai 1',
            'gpu_endpoint'  => 'http://192.168.1.200:8000/api/v1/fit-prediction',
            'camera_source' => 'camera_0',
            'status'        => 'online',
            'is_active'     => true,
        ]);

        // Seed test activity
        FitAndGoActivity::create([
            'name'            => 'Mountaineering',
            'slug'            => 'mountaineering',
            'care_mc_level_2' => 'MOUNTAINEERING',
            'image'           => 'https://example.com/mountaineering.jpg',
            'sort_order'      => 1,
            'is_active'       => true,
        ]);

        // Seed test category
        FitAndGoCategory::create([
            'code'             => 'apparel',
            'name'             => 'Apparel',
            'display_name'     => 'Apparel',
            'mc_level'         => 'MC 4 (Apparel)',
            'mc_keywords'      => 'shirt, kaos, jacket, jaket, top',
            'background_image' => 'https://example.com/bg-apparel.jpg',
            'sort_order'       => 2,
            'is_active'        => true,
        ]);
    }

    public function test_api_can_get_config(): void
    {
        $response = $this->getJson('/api/v1/fit-and-go/config?device_code=fit-kiosk-01');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data'   => [
                'device_code'  => 'fit-kiosk-01',
                'device_name'  => 'Kiosk 01',
                'gpu_endpoint' => 'http://192.168.1.200:8000/api/v1/fit-prediction',
            ],
        ]);
    }

    public function test_api_can_get_activities(): void
    {
        $response = $this->getJson('/api/v1/fit-and-go/activities');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'count',
            'data' => [
                '*' => ['id', 'name', 'slug', 'image', 'care_mc_level_2'],
            ],
        ]);
        $response->assertJsonFragment(['slug' => 'mountaineering']);
    }

    public function test_api_can_get_categories(): void
    {
        $response = $this->getJson('/api/v1/fit-and-go/categories');

        $response->assertStatus(200);
        $response->assertJsonFragment(['code' => 'apparel', 'display_name' => 'Apparel']);
    }

    public function test_api_products_filter_and_visibility(): void
    {
        // Product 1: Apparel (visible)
        $prod1 = Product::create([
            'sku'         => 'JKT-001',
            'name'        => 'Jaket Eiger Torrent',
            'description' => 'Jaket mountaineering tahan cuaca ekstrem',
            'price'       => 750000,
            'stock'       => 5,
        ]);

        // Product 2: Apparel (hidden by staff)
        $prod2 = Product::create([
            'sku'         => 'SHIRT-002',
            'name'        => 'Kaos Eiger Mountain',
            'description' => 'Kaos mountaineering outdoor',
            'price'       => 199000,
            'stock'       => 12,
        ]);

        FitAndGoItemVisibility::create([
            'product_id'    => $prod2->id,
            'category_code' => 'apparel',
            'is_visible'    => false,
        ]);

        $response = $this->getJson('/api/v1/fit-and-go/products?category=apparel&activity=mountaineering');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'count'  => 1,
        ]);
        $response->assertJsonFragment(['sku' => 'JKT-001']);
        $response->assertJsonMissing(['sku' => 'SHIRT-002']);
    }

    public function test_api_search(): void
    {
        Product::create([
            'sku'      => 'SRCH-001',
            'name'     => 'Special Polar Vest',
            'category' => 'Apparel',
            'price'    => 350000,
            'stock'    => 3,
        ]);

        $response = $this->getJson('/api/v1/fit-and-go/search?q=Polar');

        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'SRCH-001']);
    }

    public function test_api_heartbeat(): void
    {
        $response = $this->postJson('/api/v1/fit-and-go/heartbeat', [
            'device_code' => 'fit-kiosk-01',
            'status'      => 'online',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data'   => [
                'device_code' => 'fit-kiosk-01',
                'status'      => 'online',
            ],
        ]);
    }
}
