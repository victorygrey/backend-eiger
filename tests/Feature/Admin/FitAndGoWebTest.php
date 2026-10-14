<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FitAndGoWebTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConfiguresFitAndGoProducts;

    private function staticDevice(int $floor): FitAndGoDevice
    {
        return FitAndGoDevice::where('device_code', FitAndGoDevice::STATIC_SLOTS[$floor]['device_code'])->firstOrFail();
    }

    public function test_fit_and_go_opens_product_config_and_only_lists_selected_ai_products(): void
    {
        $device = $this->staticDevice(1);
        $selected = Product::factory()->create([
            'sku' => '910000111', 'name' => 'Summit Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => false, 'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);
        $notSelected = Product::factory()->create([
            'sku' => '910000112', 'name' => 'City Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => false, 'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);

        $this->withSession(['products.channel_settings_unlocked' => true])
            ->patch(route('admin.products.channel.update', $selected), [
                'channel' => 'ai_fit_and_go_active', 'active' => true,
            ])->assertRedirect();

        $this->get(route('admin.fit-and-go.index', ['device_id' => $device->id]))
            ->assertOk()
            ->assertSee('Config Product')
            ->assertSee('Top')
            ->assertSee($selected->name)
            ->assertDontSee($notSelected->name);
    }

    public function test_selected_products_are_saved_independently_for_each_floor_device(): void
    {
        $floorOne = $this->staticDevice(1);
        $floorTwo = $this->staticDevice(2);
        $product = Product::factory()->create([
            'sku' => '910000113', 'name' => 'Floor One Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => true, 'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);

        $this->configureFitProduct($product);
        $this->put(route('admin.fit-and-go.product-config.update', $floorOne), [
            'selection_mode' => 'selected', 'product_ids' => [$product->id],
        ])->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'products', 'device_id' => $floorOne->id]));

        $this->assertDatabaseHas('fit_and_go_item_visibilities', [
            'device_id' => $floorOne->id, 'product_id' => $product->id,
            'category_code' => 'apparel', 'is_visible' => true,
        ]);
        $this->assertDatabaseMissing('fit_and_go_item_visibilities', [
            'device_id' => $floorTwo->id, 'product_id' => $product->id,
        ]);
    }

    public function test_latest_mode_is_saved_without_erasing_previous_manual_selection(): void
    {
        $device = $this->staticDevice(3);
        $product = Product::factory()->create([
            'sku' => '910000114', 'name' => 'Saved Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => true, 'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);
        FitAndGoItemVisibility::create([
            'device_id' => $device->id, 'product_id' => $product->id,
            'category_code' => 'apparel', 'is_visible' => true,
        ]);

        $this->put(route('admin.fit-and-go.product-config.update', $device), [
            'selection_mode' => 'latest',
        ])->assertRedirect();

        $this->assertDatabaseHas('fit_and_go_devices', [
            'id' => $device->id, 'product_selection_mode' => 'latest',
        ]);
        $this->assertDatabaseHas('fit_and_go_item_visibilities', [
            'device_id' => $device->id, 'product_id' => $product->id,
        ]);
    }

    public function test_kiosk_config_tab_replaces_activity_and_old_device_tabs(): void
    {
        $device = $this->staticDevice(1);

        $this->get(route('admin.fit-and-go.index', ['tab' => 'kiosks']))
            ->assertOk()
            ->assertSee('Konfig Kiosk')
            ->assertSee($device->name)
            ->assertSee('Tiga kiosk lantai bersifat tetap')
            ->assertSee('Kode Aktivasi Aktif')
            ->assertDontSee('Aktivitas EIGER')
            ->assertDontSee('Kategori &amp; Visibilitas', false)
            ->assertDontSee('Workstation GPU')
            ->assertDontSee('Sumber Kamera');

        $this->get(route('admin.fit-and-go.index', ['tab' => 'devices']))
            ->assertOk()
            ->assertSee('Konfig Kiosk')
            ->assertSee($device->name);
    }

    public function test_device_form_only_contains_identity_location_and_activation_security(): void
    {
        $device = $this->staticDevice(2);
        $device->update([
            'activation_code_hash' => Hash::make('FLOOR-02'),
            'activation_code_encrypted' => 'FLOOR-02',
        ]);

        $this->get(route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog']))
            ->assertOk()
            ->assertSee('Slug URL / Device Identifier')
            ->assertSee('Aktivasi &amp; Keamanan', false)
            ->assertSee('value="FLOOR-02"', false)
            ->assertSee('readonly', false)
            ->assertDontSee('name="name"', false)
            ->assertDontSee('name="device_code"', false)
            ->assertDontSee('name="is_active"', false)
            ->assertDontSee('Kategori &amp; Visibilitas', false)
            ->assertDontSee('EIGER Activity')
            ->assertDontSee('GPU')
            ->assertDontSee('Sumber Kamera');
    }

    public function test_kiosk_slots_are_fixed_and_create_delete_routes_are_unavailable(): void
    {
        $this->assertSame(3, FitAndGoDevice::whereIn('device_code', FitAndGoDevice::staticDeviceCodes())->count());
        $this->get('/admin/fit-and-go/devices/create')->assertStatus(405);
        $this->post('/admin/fit-and-go/devices')->assertNotFound();
        $this->delete('/admin/fit-and-go/devices/'.$this->staticDevice(1)->id)->assertStatus(405);
    }

    public function test_updating_activation_code_revokes_existing_device_token(): void
    {
        $device = $this->staticDevice(2);
        $device->update([
            'activation_code_hash' => Hash::make('OLD-CODE'),
            'activation_code_encrypted' => 'OLD-CODE',
            'device_token_hash' => Hash::make('old-token'),
        ]);

        $this->put(route('admin.fit-and-go.devices.update', $device), [
            'name' => 'Nama Tidak Boleh Berubah',
            'device_code' => 'slug-tidak-boleh-berubah',
            'location' => 'Lokasi Tidak Boleh Berubah',
            'activation_code' => 'NEW-CODE',
            'is_active' => 0,
        ])->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'kiosks']));

        $device->refresh();
        $this->assertSame(FitAndGoDevice::STATIC_SLOTS[2]['name'], $device->name);
        $this->assertSame(FitAndGoDevice::STATIC_SLOTS[2]['device_code'], $device->device_code);
        $this->assertSame(FitAndGoDevice::STATIC_SLOTS[2]['location'], $device->location);
        $this->assertTrue($device->is_active);
        $this->assertTrue(Hash::check('NEW-CODE', $device->activation_code_hash));
        $this->assertSame('NEW-CODE', $device->activation_code_encrypted);
        $this->assertNull($device->device_token_hash);
    }

    public function test_saving_the_same_visible_activation_code_keeps_existing_pairing_token(): void
    {
        $tokenHash = Hash::make('paired-token');
        $device = $this->staticDevice(3);
        $device->update([
            'activation_code_hash' => Hash::make('VISIBLE-03'),
            'activation_code_encrypted' => 'VISIBLE-03',
            'device_token_hash' => $tokenHash,
        ]);

        $this->put(route('admin.fit-and-go.devices.update', $device), [
            'activation_code' => 'VISIBLE-03',
        ])->assertRedirect();

        $this->assertSame($tokenHash, $device->fresh()->device_token_hash);
    }

    public function test_removed_activity_and_legacy_product_configuration_routes_are_unavailable(): void
    {
        $device = FitAndGoDevice::create([
            'name' => 'Kiosk', 'device_code' => 'kiosk-route-test',
            'status' => 'offline', 'is_active' => true,
        ]);

        $this->get('/admin/fit-and-go/activities/create')->assertNotFound();
        $this->put("/admin/fit-and-go/devices/{$device->id}/catalog/hat", [])->assertNotFound();
        $this->put("/admin/fit-and-go/devices/{$device->id}/activities/hiking", [])->assertNotFound();
    }
}
