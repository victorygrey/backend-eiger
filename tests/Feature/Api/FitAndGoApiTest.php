<?php

namespace Tests\Feature\Api;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FitAndGoApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed test device
        FitAndGoDevice::create([
            'name' => 'Kiosk 01',
            'device_code' => 'fit-kiosk-01',
            'location' => 'Lantai 1',
            'gpu_endpoint' => 'http://192.168.1.200:8000/api/v1/fit-prediction',
            'camera_source' => 'camera_0',
            'status' => 'online',
            'is_active' => true,
        ]);

        // Seed test activity
        FitAndGoActivity::create([
            'name' => 'Mountaineering',
            'slug' => 'mountaineering',
            'care_mc_level_2' => 'MOUNTAINEERING',
            'image' => 'https://example.com/mountaineering.jpg',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // Seed test category
        FitAndGoCategory::create([
            'code' => 'apparel',
            'name' => 'Apparel',
            'display_name' => 'Apparel',
            'mc_level' => 'MC 4 (Apparel)',
            'mc_keywords' => 'shirt, kaos, jacket, jaket, top',
            'background_image' => 'https://example.com/bg-apparel.jpg',
            'sort_order' => 2,
            'is_active' => true,
        ]);
    }

    public function test_api_can_get_config(): void
    {
        $response = $this->getJson('/api/v1/fit-and-go/config?device_code=fit-kiosk-01');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'device_code' => 'fit-kiosk-01',
                'device_name' => 'Kiosk 01',
                'gpu_endpoint' => 'http://192.168.1.200:8000/api/v1/fit-prediction',
            ],
        ]);
    }

    public function test_kiosk_slug_returns_its_categories_and_activity_recommendations(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-kiosk-01')->firstOrFail();
        $activity = FitAndGoActivity::where('slug', 'mountaineering')->firstOrFail();
        $product = Product::factory()->create([
            'sku' => '910000999',
            'name' => 'Kiosk Expedition Jacket',
            'price' => 999000,
            'stock' => 8,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => '910000999001',
            'name' => 'Kiosk Expedition Jacket Black M',
            'color' => 'Black',
            'size' => 'M',
            'price' => 999000,
            'stock' => 8,
        ]);
        FitAndGoItemVisibility::create([
            'device_id' => $device->id,
            'product_id' => $product->id,
            'category_code' => 'apparel',
            'is_visible' => true,
        ]);
        DB::table('fit_and_go_device_activity_products')->insert([
            'device_id' => $device->id,
            'activity_id' => $activity->id,
            'product_id' => $product->id,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/fit-and-go/kiosks/fit-kiosk-01');

        $response->assertOk()
            ->assertJsonPath('data.kiosk.slug', 'fit-kiosk-01')
            ->assertJsonPath('data.categories.0.code', 'apparel')
            ->assertJsonPath('data.categories.0.shown_items.0.sku', '910000999');

        $activityData = collect($response->json('data.activities'))->firstWhere('slug', 'mountaineering');
        $this->assertSame('910000999001', data_get($activityData, 'recommended_items.0.variants.0.sku'));
    }

    public function test_unknown_kiosk_slug_returns_clear_json_error(): void
    {
        $this->getJson('/api/v1/fit-and-go/kiosks/not-a-kiosk')
            ->assertNotFound()
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('data', null);
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
        // Product 1: explicitly assigned to category and activity
        $prod1 = Product::create([
            'sku' => 'JKT-001',
            'name' => 'Jaket Eiger Torrent',
            'description' => 'Jaket mountaineering tahan cuaca ekstrem',
            'price' => 750000,
            'stock' => 5,
        ]);

        // Product 2: Apparel (hidden by staff)
        $prod2 = Product::create([
            'sku' => 'SHIRT-002',
            'name' => 'Kaos Eiger Mountain',
            'description' => 'Kaos mountaineering outdoor',
            'price' => 199000,
            'stock' => 12,
        ]);

        $this->getJson('/api/v1/fit-and-go/products?category=apparel&activity=mountaineering')
            ->assertJson(['count' => 0]);

        FitAndGoItemVisibility::create([
            'product_id' => $prod1->id,
            'category_code' => 'apparel',
            'is_visible' => true,
        ]);
        FitAndGoActivity::where('slug', 'mountaineering')->first()->recommendedProducts()->sync([$prod1->id]);
        FitAndGoItemVisibility::create([
            'product_id' => $prod2->id,
            'category_code' => 'apparel',
            'is_visible' => false,
        ]);

        $response = $this->getJson('/api/v1/fit-and-go/products?category=apparel&activity=mountaineering');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'count' => 1,
        ]);
        $response->assertJsonFragment(['sku' => 'JKT-001']);
        $response->assertJsonMissing(['sku' => 'SHIRT-002']);
    }

    public function test_api_search(): void
    {
        $product = Product::create([
            'sku' => 'SRCH-001',
            'name' => 'Special Polar Vest',
            'category' => 'Apparel',
            'price' => 350000,
            'stock' => 3,
        ]);
        FitAndGoItemVisibility::create([
            'product_id' => $product->id,
            'category_code' => 'apparel',
            'is_visible' => true,
        ]);

        $response = $this->getJson('/api/v1/fit-and-go/search?q=Polar');

        $response->assertStatus(200);
        $response->assertJsonFragment(['sku' => 'SRCH-001']);
    }

    public function test_api_heartbeat(): void
    {
        $response = $this->postJson('/api/v1/fit-and-go/heartbeat', [
            'device_code' => 'fit-kiosk-01',
            'status' => 'online',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'device_code' => 'fit-kiosk-01',
                'status' => 'online',
            ],
        ]);
    }

    public function test_activity_products_are_scoped_to_requested_device(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-kiosk-01')->firstOrFail();
        $activity = FitAndGoActivity::where('slug', 'mountaineering')->firstOrFail();
        $product = Product::factory()->create(['sku' => '910000004', 'pim_catalog_active' => true, 'is_discontinued' => false]);
        DB::table('fit_and_go_device_activity_products')->insert([
            'device_id' => $device->id, 'activity_id' => $activity->id, 'product_id' => $product->id,
            'sort_order' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->getJson('/api/v1/fit-and-go/products?device_code=fit-kiosk-01&activity=mountaineering')
            ->assertOk()->assertJson(['count' => 1])->assertJsonFragment(['sku' => '910000004']);
    }

    public function test_activity_recommendations_and_shown_item_return_complete_pim_and_care_detail(): void
    {
        $activity = FitAndGoActivity::where('slug', 'mountaineering')->firstOrFail();
        $product = Product::factory()->create([
            'sku' => '910000123',
            'name' => 'Expedition Shell',
            'price' => 1299000,
            'stock' => 7,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
            'pim_payload' => [
                'generic' => '910000123',
                'name' => 'Expedition Shell',
                'technology' => [['name' => 'Storm Shield', 'description' => 'Perlindungan cuaca']],
                'activity' => [['name' => 'Mountaineering', 'selected' => true, 'rating' => 5]],
                'specification' => [['name' => 'Waterproof Rating', 'value' => '10K']],
                'customAtributes' => [['attributeCode' => 'fit', 'value' => 'Regular']],
            ],
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => '910000123001',
            'name' => 'Expedition Shell Black M',
            'color' => 'Black',
            'size' => 'M',
            'price' => 1299000,
            'stock' => 7,
        ]);
        $activity->recommendedProducts()->sync([$product->id => ['sort_order' => 0]]);

        $this->getJson('/api/v1/fit-and-go/activities/mountaineering/recommendations')
            ->assertOk()
            ->assertJsonPath('activity.slug', 'mountaineering')
            ->assertJsonPath('data.0.sku', '910000123')
            ->assertJsonPath('data.0.variants.0.sku', '910000123001')
            ->assertJsonPath('data.0.pricing.total_stock', 7)
            ->assertJsonPath('data.0.technologies.0.name', 'Storm Shield')
            ->assertJsonPath('data.0.custom_attributes.0.attributeCode', 'fit');

        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.name', 'Expedition Shell')
            ->assertJsonPath('data.available_sizes.0', 'M')
            ->assertJsonPath('data.available_colors.0', 'Black');
    }
}
