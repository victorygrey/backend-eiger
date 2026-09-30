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

    public function test_fit_and_go_opens_product_config_and_only_lists_selected_ai_products(): void
    {
        $device = FitAndGoDevice::create([
            'name' => 'AI Fit & Go Lantai 1', 'device_code' => 'fit-floor-1',
            'location' => 'Lantai 1', 'status' => 'online', 'is_active' => true,
        ]);
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
        $floorOne = FitAndGoDevice::create([
            'name' => 'AI Fit & Go Lantai 1', 'device_code' => 'fit-floor-1',
            'location' => 'Lantai 1', 'status' => 'online', 'is_active' => true,
        ]);
        $floorTwo = FitAndGoDevice::create([
            'name' => 'AI Fit & Go Lantai 2', 'device_code' => 'fit-floor-2',
            'location' => 'Lantai 2', 'status' => 'online', 'is_active' => true,
        ]);
        $product = Product::factory()->create([
            'sku' => '910000113', 'name' => 'Floor One Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => true, 'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);

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
        $device = FitAndGoDevice::create([
            'name' => 'AI Fit & Go Lantai 3', 'device_code' => 'fit-floor-3',
            'location' => 'Lantai 3', 'status' => 'online', 'is_active' => true,
        ]);
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
        $device = FitAndGoDevice::create([
            'name' => 'Kiosk Lantai 1', 'device_code' => 'lantai-1',
            'location' => 'Lantai 1', 'status' => 'offline', 'is_active' => true,
            'activation_code_hash' => Hash::make('FLOOR-01'),
        ]);

        $this->get(route('admin.fit-and-go.index', ['tab' => 'kiosks']))
            ->assertOk()
            ->assertSee('Konfig Kiosk')
            ->assertSee($device->name)
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
        $device = FitAndGoDevice::create([
            'name' => 'Kiosk Lantai 2', 'device_code' => 'lantai-2',
            'location' => 'Lantai 2', 'status' => 'offline', 'is_active' => true,
            'activation_code_hash' => Hash::make('FLOOR-02'),
            'activation_code_encrypted' => 'FLOOR-02',
        ]);

        $this->get(route('admin.fit-and-go.devices.edit', ['device' => $device, 'tab' => 'catalog']))
            ->assertOk()
            ->assertSee('Slug URL / Device Identifier')
            ->assertSee('Aktivasi &amp; Keamanan', false)
            ->assertSee('value="FLOOR-02"', false)
            ->assertDontSee('Kategori &amp; Visibilitas', false)
            ->assertDontSee('EIGER Activity')
            ->assertDontSee('GPU')
            ->assertDontSee('Sumber Kamera');
    }

    public function test_can_create_secure_kiosk_device(): void
    {
        $this->post(route('admin.fit-and-go.devices.store'), [
            'name' => 'Lobby Kiosk',
            'device_code' => 'kiosk-lobby',
            'location' => 'Lantai 1 Lobby',
            'activation_code' => 'LOBBY-01',
            'is_active' => 1,
        ])->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'kiosks']));

        $device = FitAndGoDevice::where('device_code', 'kiosk-lobby')->firstOrFail();
        $this->assertSame('offline', $device->status);
        $this->assertTrue(Hash::check('LOBBY-01', $device->activation_code_hash));
        $this->assertSame('LOBBY-01', $device->activation_code_encrypted);
        $this->assertNotSame('LOBBY-01', $device->getRawOriginal('activation_code_encrypted'));
    }

    public function test_updating_activation_code_revokes_existing_device_token(): void
    {
        $device = FitAndGoDevice::create([
            'name' => 'Legacy Kiosk', 'device_code' => 'KIOSK-02',
            'location' => 'Lantai 2', 'status' => 'online', 'is_active' => true,
            'activation_code_hash' => Hash::make('OLD-CODE'),
            'activation_code_encrypted' => 'OLD-CODE',
            'device_token_hash' => Hash::make('old-token'),
        ]);

        $this->put(route('admin.fit-and-go.devices.update', $device), [
            'name' => 'Legacy Kiosk Updated',
            'device_code' => 'KIOSK-02',
            'location' => 'Lantai 2',
            'activation_code' => 'NEW-CODE',
            'is_active' => 1,
        ])->assertRedirect(route('admin.fit-and-go.index', ['tab' => 'kiosks']));

        $device->refresh();
        $this->assertSame('Legacy Kiosk Updated', $device->name);
        $this->assertTrue(Hash::check('NEW-CODE', $device->activation_code_hash));
        $this->assertSame('NEW-CODE', $device->activation_code_encrypted);
        $this->assertNull($device->device_token_hash);
    }

    public function test_saving_the_same_visible_activation_code_keeps_existing_pairing_token(): void
    {
        $tokenHash = Hash::make('paired-token');
        $device = FitAndGoDevice::create([
            'name' => 'Paired Kiosk', 'device_code' => 'paired-kiosk',
            'location' => 'Lantai 3', 'status' => 'online', 'is_active' => true,
            'activation_code_hash' => Hash::make('VISIBLE-03'),
            'activation_code_encrypted' => 'VISIBLE-03',
            'device_token_hash' => $tokenHash,
        ]);

        $this->put(route('admin.fit-and-go.devices.update', $device), [
            'name' => 'Paired Kiosk Updated',
            'device_code' => 'paired-kiosk',
            'location' => 'Lantai 3',
            'activation_code' => 'VISIBLE-03',
            'is_active' => 1,
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
