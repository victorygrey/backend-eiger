<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\RfidTag;
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
        $response->assertSee('Scan Terakhir');
        $response->assertSee('E280116060000204AABBCCDD');
        $response->assertSee($tag->created_at->format('d M Y'));
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
}
