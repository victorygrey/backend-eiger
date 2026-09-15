<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FitAndGoWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic category
        FitAndGoCategory::create([
            'code'             => 'hat',
            'name'             => 'Hat',
            'display_name'     => 'Hat',
            'mc_level'         => 'MC 3 (Headwear)',
            'mc_keywords'      => 'topi, hat, cap, headwear',
            'background_image' => 'https://example.com/bg-hat.jpg',
            'sort_order'       => 1,
            'is_active'        => true,
        ]);
    }

    public function test_fit_and_go_dashboard_loads_devices_tab(): void
    {
        FitAndGoDevice::create([
            'name'          => 'Main Kiosk',
            'device_code'   => 'kiosk-01',
            'location'      => 'Floor 1',
            'status'        => 'online',
            'is_active'     => true,
        ]);

        $response = $this->get(route('admin.fit-and-go.index', ['tab' => 'devices']));

        $response->assertStatus(200);
        $response->assertSee('Perangkat Kiosk');
        $response->assertSee('Main Kiosk');
        $response->assertSee('kiosk-01');
    }

    public function test_fit_and_go_dashboard_loads_activities_tab(): void
    {
        FitAndGoActivity::create([
            'name'            => 'Mountaineering',
            'slug'            => 'mountaineering',
            'care_mc_level_2' => 'MOUNTAINEERING',
            'sort_order'      => 1,
            'is_active'       => true,
        ]);

        $response = $this->get(route('admin.fit-and-go.index', ['tab' => 'activities']));

        $response->assertStatus(200);
        $response->assertSee('Mountaineering');
        $response->assertSee('MOUNTAINEERING');
    }

    public function test_can_create_device(): void
    {
        $response = $this->post(route('admin.fit-and-go.devices.store'), [
            'name'          => 'Lobby Kiosk',
            'device_code'   => 'kiosk-lobby',
            'location'      => 'Lantai 1 Lobby',
            'ip_address'    => '192.168.1.100',
            'gpu_endpoint'  => 'http://192.168.1.200:8000/api/v1/fit-prediction',
            'camera_source' => 'cam_0',
            'status'        => 'online',
            'is_active'     => 1,
        ]);

        $response->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'devices']));
        $this->assertDatabaseHas('fit_and_go_devices', [
            'device_code' => 'kiosk-lobby',
            'name'        => 'Lobby Kiosk',
        ]);
    }

    public function test_can_ping_device(): void
    {
        $device = FitAndGoDevice::create([
            'name'        => 'Test Kiosk',
            'device_code' => 'test-kiosk',
            'status'      => 'offline',
        ]);

        $response = $this->post(route('admin.fit-and-go.devices.ping', $device));

        $response->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'devices']));
        $this->assertDatabaseHas('fit_and_go_devices', [
            'id'     => $device->id,
            'status' => 'online',
        ]);
    }

    public function test_existing_uppercase_device_identifier_can_be_edited(): void
    {
        $device = FitAndGoDevice::create([
            'name' => 'Legacy Kiosk',
            'device_code' => 'KIOSK-02',
            'status' => 'online',
        ]);
        $this->put(route('admin.fit-and-go.devices.update', $device), [
            'name' => 'Legacy Kiosk Updated',
            'device_code' => 'KIOSK-02',
            'status' => 'online',
        ])->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'devices']));
        $this->assertDatabaseHas('fit_and_go_devices', ['id' => $device->id, 'name' => 'Legacy Kiosk Updated']);
    }

    public function test_can_create_and_update_activity(): void
    {
        $createResp = $this->post(route('admin.fit-and-go.activities.store'), [
            'name'            => 'Trail Running',
            'slug'            => 'trail-running',
            'care_mc_level_2' => 'RUNNING',
            'sort_order'      => 5,
            'is_active'       => 1,
        ]);

        $createResp->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'activities']));
        $this->assertDatabaseHas('fit_and_go_activities', [
            'slug' => 'trail-running',
            'name' => 'Trail Running',
        ]);

        $activity = FitAndGoActivity::where('slug', 'trail-running')->first();

        $updateResp = $this->put(route('admin.fit-and-go.activities.update', $activity), [
            'name'            => 'Trail Running & Trekking',
            'slug'            => 'trail-running',
            'care_mc_level_2' => 'RUNNING_TREK',
            'sort_order'      => 6,
            'is_active'       => 1,
        ]);

        $updateResp->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'activities']));
        $this->assertDatabaseHas('fit_and_go_activities', [
            'id'              => $activity->id,
            'name'            => 'Trail Running & Trekking',
            'care_mc_level_2' => 'RUNNING_TREK',
        ]);
    }

    public function test_can_toggle_product_visibility(): void
    {
        $product = Product::create([
            'sku'      => 'HAT-001',
            'name'     => 'Topi Rimba Eiger',
            'category' => 'Headwear',
            'price'    => 150000,
            'stock'    => 10,
        ]);

        // Toggle to hidden (0)
        $response = $this->post(route('admin.fit-and-go.items.visibility'), [
            'product_id'    => $product->id,
            'category_code' => 'hat',
            'is_visible'    => 0,
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('fit_and_go_item_visibilities', [
            'product_id'    => $product->id,
            'category_code' => 'hat',
            'is_visible'    => 0,
        ]);

        // Toggle back to visible (1)
        $this->post(route('admin.fit-and-go.items.visibility'), [
            'product_id'    => $product->id,
            'category_code' => 'hat',
            'is_visible'    => 1,
        ]);

        $this->assertDatabaseHas('fit_and_go_item_visibilities', [
            'product_id'    => $product->id,
            'category_code' => 'hat',
            'is_visible'    => 1,
        ]);
    }

    public function test_activity_recommendations_are_selected_manually(): void
    {
        $product = Product::factory()->create(['is_discontinued' => false]);
        $response = $this->post(route('admin.fit-and-go.activities.store'), [
            'name' => 'City Walking',
            'slug' => 'city-walking',
            'recommended_product_ids' => [$product->id],
            'is_active' => 1,
        ]);
        $response->assertRedirect();
        $activity = FitAndGoActivity::where('slug', 'city-walking')->firstOrFail();
        $this->assertEquals([$product->id], $activity->recommendedProducts()->pluck('products.id')->all());
        $this->get(route('admin.fit-and-go.activities.edit', $activity))
            ->assertOk()->assertSee('recommended_product_ids[]');
    }

    public function test_kiosk_category_uses_filtered_dual_picker_and_saves_selection(): void
    {
        $device = FitAndGoDevice::create(['name' => 'Kiosk', 'device_code' => 'kiosk-filter', 'status' => 'online']);
        $hat = Product::factory()->create(['sku' => '910000001', 'name' => 'Rimba Bucket Hat', 'pim_catalog_active' => true,
            'pim_payload' => ['customAtributes' => [['attributeCode' => 'category', 'value' => 'Hat']]]]);
        $pants = Product::factory()->create(['sku' => '910000002', 'name' => 'Cargo Pants', 'pim_catalog_active' => true,
            'pim_payload' => ['customAtributes' => [['attributeCode' => 'category', 'value' => 'Pants']]]]);

        $this->get(route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog', 'category' => 'hat']))
            ->assertOk()->assertSee('category-picker')->assertSee($hat->name)->assertDontSee($pants->name);
        $this->put(route('admin.fit-and-go.devices.catalog.sync', [$device, FitAndGoCategory::where('code', 'hat')->first()]), ['product_ids' => [$hat->id]])
            ->assertRedirect();
        $this->assertDatabaseHas('fit_and_go_item_visibilities', ['device_id' => $device->id, 'product_id' => $hat->id, 'category_code' => 'hat', 'is_visible' => true]);
    }

    public function test_kiosk_activity_tab_saves_ordered_products_per_device(): void
    {
        $device = FitAndGoDevice::create(['name' => 'Kiosk', 'device_code' => 'kiosk-activity', 'status' => 'online']);
        $activity = FitAndGoActivity::create(['name' => 'Camping', 'slug' => 'camping', 'sort_order' => 1, 'is_active' => true]);
        $product = Product::factory()->create(['sku' => '910000003', 'name' => 'Camping Jacket', 'pim_catalog_active' => true,
            'pim_payload' => ['customAtributes' => [['attributeCode' => 'activity', 'value' => 'Camping']]]]);

        $this->get(route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'activities', 'activity' => 'camping']))
            ->assertOk()->assertSee('EIGER Activity')->assertSee($product->name);
        $this->put(route('admin.fit-and-go.devices.activities.sync', [$device, $activity]), ['product_ids' => [$product->id]])->assertRedirect();
        $this->assertTrue(DB::table('fit_and_go_device_activity_products')->where([
            'device_id' => $device->id, 'activity_id' => $activity->id, 'product_id' => $product->id,
        ])->exists());
    }
}
