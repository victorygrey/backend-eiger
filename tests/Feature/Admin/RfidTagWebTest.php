<?php

namespace Tests\Feature\Admin;

use App\Models\LedAmbienceItem;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfidTagWebTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_access_rfid_tags_page(): void
    {
        $response = $this->get(route('admin.rfid-tags.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_rfid_tags_index(): void
    {
        $tag = RfidTag::create([
            'uid' => 'E280116060000204AABBCCDD',
            'last_scanned_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.index'));

        $response->assertStatus(200);
        $response->assertSee('Master RFID Tags');
        $response->assertSee('UID / Label RFID');
        $response->assertSee('Produk / SKU Terhubung');
        $response->assertSee('LED Ambience');
        $response->assertSee('Table Expedition');
        $response->assertSee('Scan Terakhir');
        $response->assertSee('E280116060000204AABBCCDD');
    }

    public function test_search_filters_rfid_tags_by_uid(): void
    {
        RfidTag::create(['uid' => 'E28011606000020411111111']);
        RfidTag::create(['uid' => 'E28011606000020422222222']);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.index', [
            'search' => '11111111',
        ]));

        $response->assertStatus(200);
        $response->assertSee('E28011606000020411111111');
        $response->assertDontSee('E28011606000020422222222');
    }

    public function test_user_can_view_create_rfid_tag_form(): void
    {
        $product = Product::factory()->create([
            'sku' => '910001234',
            'name' => 'EIGER Test Product',
            'is_discontinued' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.create'));

        $response->assertStatus(200);
        $response->assertSee('Daftarkan RFID Baru');
        $response->assertSee('name="product_id"', false);
        $response->assertSee($product->sku);
    }

    public function test_user_can_store_new_rfid_tag(): void
    {
        $product = Product::factory()->create([
            'sku' => '910009999',
            'is_discontinued' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.rfid-tags.store'), [
            'uid' => 'E28011606000020499998888',
            'product_id' => $product->id,
        ]);

        $response->assertRedirect(route('admin.rfid-tags.index'));
        $this->assertDatabaseHas('rfid_tags', [
            'uid' => 'E28011606000020499998888',
            'product_id' => $product->id,
        ]);
    }

    public function test_user_can_edit_and_delete_rfid_tag(): void
    {
        $product = Product::factory()->create([
            'sku' => '910001111',
            'is_discontinued' => false,
        ]);
        $tag = RfidTag::create(['uid' => 'E280116060000204ABCDEF12']);

        $this->actingAs($this->admin)->get(route('admin.rfid-tags.index'))->assertOk();
        $this->get(route('admin.rfid-tags.edit', $tag))->assertOk()->assertSee('name="product_id"', false);
        $this->put(route('admin.rfid-tags.update', $tag), [
            'uid' => 'E280116060000204ABCDEF12',
            'name' => 'Tag uji',
            'product_id' => $product->id,
        ])->assertRedirect(route('admin.rfid-tags.index'));
        $this->assertDatabaseHas('rfid_tags', ['id' => $tag->id, 'name' => 'Tag uji', 'product_id' => $product->id]);

        $this->delete(route('admin.rfid-tags.destroy', $tag))
            ->assertRedirect(route('admin.rfid-tags.index'));
        $this->assertDatabaseMissing('rfid_tags', ['id' => $tag->id]);
    }

    public function test_search_and_mapping_filter_include_product_sku(): void
    {
        $product = Product::factory()->create([
            'sku' => '910007777',
            'name' => 'EIGER RFID Search Product',
            'is_discontinued' => false,
        ]);
        RfidTag::create(['uid' => 'E28011606000020477777777', 'product_id' => $product->id]);
        RfidTag::create(['uid' => 'E28011606000020488888888']);

        $response = $this->actingAs($this->admin)->get(route('admin.rfid-tags.index', [
            'search' => '910007777',
            'mapping' => 'mapped',
        ]));

        $response->assertOk()
            ->assertSee('E28011606000020477777777')
            ->assertDontSee('E28011606000020488888888');
    }

    public function test_multiple_physical_tags_can_map_to_the_same_product(): void
    {
        $product = Product::factory()->create(['sku' => '910005555', 'is_discontinued' => false]);

        foreach (['E28011606000020455550001', 'E28011606000020455550002'] as $uid) {
            $this->actingAs($this->admin)->post(route('admin.rfid-tags.store'), [
                'uid' => $uid,
                'product_id' => $product->id,
            ])->assertRedirect(route('admin.rfid-tags.index'));
        }

        $this->assertSame(2, RfidTag::where('product_id', $product->id)->count());
    }

    public function test_rfid_channel_switches_are_locked_and_control_real_device_mappings(): void
    {
        $product = Product::factory()->create(['sku' => '910006666', 'is_discontinued' => false]);
        $tag = RfidTag::create(['uid' => 'E28011606000020466660001', 'product_id' => $product->id]);

        $this->actingAs($this->admin)->patch(route('admin.rfid-tags.channel.update', $tag), [
            'channel' => 'led_ambience',
            'active' => true,
        ])->assertStatus(423);

        $this->patch(route('admin.rfid-tags.channel-lock.update'), ['unlocked' => true])
            ->assertRedirect();

        $this->patch(route('admin.rfid-tags.channel.update', $tag), [
            'channel' => 'led_ambience',
            'active' => true,
        ])->assertRedirect();
        $this->assertDatabaseHas('led_ambience_items', [
            'rfid_tag' => $tag->uid,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $ledMapping = LedAmbienceItem::where('rfid_tag', $tag->uid)->firstOrFail();
        $ledMapping->update(['notes' => 'Konfigurasi tetap disimpan']);
        $this->patch(route('admin.rfid-tags.channel.update', $tag), [
            'channel' => 'led_ambience',
            'active' => false,
        ])->assertRedirect();
        $this->assertDatabaseHas('led_ambience_items', [
            'id' => $ledMapping->id,
            'notes' => 'Konfigurasi tetap disimpan',
            'is_active' => false,
        ]);

        $this->patch(route('admin.rfid-tags.channel.update', $tag), [
            'channel' => 'table_expedition',
            'active' => true,
        ])->assertRedirect();
        $this->assertTrue(TableExpeditionItem::where('rfid_tag', $tag->uid)->firstOrFail()->is_active);
    }

    public function test_unassigned_rfid_cannot_be_enabled_for_a_device(): void
    {
        $tag = RfidTag::create(['uid' => 'E28011606000020466660002']);

        $this->actingAs($this->admin)
            ->patch(route('admin.rfid-tags.channel-lock.update'), ['unlocked' => true]);

        $this->patch(route('admin.rfid-tags.channel.update', $tag), [
            'channel' => 'table_expedition',
            'active' => true,
        ])->assertSessionHasErrors('channel');

        $this->assertDatabaseMissing('table_expedition_items', ['rfid_tag' => $tag->uid]);
    }
}
