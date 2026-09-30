<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\Tablet;
use App\Models\TabletConfigVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InteractiveTableWebTest extends TestCase
{
    use RefreshDatabase;

    private int $tabletSkuSequence = 910700000;

    private function tabletProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => (string) ++$this->tabletSkuSequence,
            'interactive_tablet_active' => true,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ], $attributes));
    }

    public function test_interactive_table_index_loads_successfully(): void
    {
        $featured = $this->tabletProduct([
            'name' => 'EIGER Streamline Daypack',
        ]);
        $rec1 = $this->tabletProduct(['name' => 'EIGER Hiking Cap']);
        $rec2 = $this->tabletProduct(['name' => 'EIGER Windbreaker']);

        $tablet = Tablet::create([
            'name'                => 'Meja Ekspedisi 01',
            'slug'                => 'meja-01',
            'location'            => 'Lantai 1 - Area Daypack',
            'featured_product_id' => $featured->id,
            'activation_code_hash'=> Hash::make('MEJA-01'),
            'config_version'      => 1,
            'is_active'           => true,
            'last_seen_at'        => now(),
        ]);
        $tablet->recommendations()->attach([$rec1->id => ['sort_order' => 0], $rec2->id => ['sort_order' => 1]]);

        $response = $this->get(route('admin.tablets.index'));

        $response->assertStatus(200);
        $response->assertSee('Interactive Table &amp; Display', false);
        $response->assertSee('Meja Ekspedisi 01');
        $response->assertSee('ID internal: meja-01');
        $response->assertSee('EIGER Streamline Daypack');
        $response->assertSee('2 produk');
        $response->assertSee('Online');
    }

    public function test_interactive_table_create_page_loads(): void
    {
        $selected = $this->tabletProduct(['name' => 'EIGER Tablet Selected']);
        Product::factory()->create([
            'sku' => '910799999',
            'name' => 'EIGER Tablet Not Selected',
            'interactive_tablet_active' => false,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ]);

        $response = $this->get(route('admin.tablets.create'));

        $response->assertStatus(200);
        $response->assertSee('Tambah Interactive Table');
        $response->assertSee('Produk Utama');
        $response->assertSee('Produk Rekomendasi');
        $response->assertSee('Aktivasi');
        $response->assertSee('Katalog mengikuti pilihan di List Product');
        $response->assertSee($selected->name);
        $response->assertDontSee('EIGER Tablet Not Selected');
    }

    public function test_interactive_table_store_saves_ordered_recommendations(): void
    {
        $featured = $this->tabletProduct();
        $rec1 = $this->tabletProduct();
        $rec2 = $this->tabletProduct();

        $response = $this->post(route('admin.tablets.store'), [
            'name'                => 'Table Expedition Hub',
            'slug'                => 'table-hub',
            'location'            => 'Ground Floor',
            'featured_product_id' => $featured->id,
            'recommendation_ids'  => [$rec2->id, $rec1->id], // explicitly ordered
            'activation_code'     => 'HUB-2026',
            'is_active'           => 1,
        ]);

        $response->assertSessionHasNoErrors();
        $tablet = Tablet::where('slug', 'table-hub')->first();
        $this->assertNotNull($tablet);
        $this->assertEquals($featured->id, $tablet->featured_product_id);
        $this->assertSame('HUB-2026', $tablet->activation_code_encrypted);
        $this->assertNotSame('HUB-2026', $tablet->getRawOriginal('activation_code_encrypted'));

        // Verify recommendation order preserved
        $recs = $tablet->recommendations()->orderBy('sort_order')->get();
        $this->assertCount(2, $recs);
        $this->assertEquals($rec2->id, $recs[0]->id);
        $this->assertEquals($rec1->id, $recs[1]->id);
    }

    public function test_interactive_table_rejects_products_not_enabled_from_list_product(): void
    {
        $featured = Product::factory()->create([
            'sku' => '910799998',
            'interactive_tablet_active' => false,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ]);
        $recommendation = $this->tabletProduct();

        $this->post(route('admin.tablets.store'), [
            'name' => 'Tablet Tidak Valid',
            'slug' => 'tablet-tidak-valid',
            'featured_product_id' => $featured->id,
            'recommendation_ids' => [$recommendation->id],
            'activation_code' => 'INVALID-01',
            'is_active' => 1,
        ])->assertSessionHasErrors('featured_product_id');

        $this->assertDatabaseMissing('tablets', ['slug' => 'tablet-tidak-valid']);
    }

    public function test_interactive_table_edit_page_loads_with_modal_preview(): void
    {
        $featured = $this->tabletProduct(['name' => 'EIGER Avalanche Jacket']);
        $rec = $this->tabletProduct(['name' => 'EIGER Cargo Pants']);

        $tablet = Tablet::create([
            'name'                => 'Display Tengah',
            'slug'                => 'display-mid',
            'featured_product_id' => $featured->id,
            'activation_code_hash'=> Hash::make('MID-01'),
            'activation_code_encrypted' => 'MID-01',
            'config_version'      => 1,
            'is_active'           => true,
        ]);
        $tablet->recommendations()->attach($rec->id, ['sort_order' => 0]);

        $response = $this->get(route('admin.tablets.edit', $tablet));

        $response->assertStatus(200);
        $response->assertSee('Display Tengah');
        $response->assertSee('EIGER Avalanche Jacket');
        $response->assertSee('EIGER Cargo Pants');
        $response->assertSee('value="MID-01"', false);
        $response->assertSee('Simulasi Tampilan Layar');
        $response->assertSee('Riwayat Versi Konfigurasi');
    }

    public function test_saving_same_visible_tablet_activation_code_does_not_revoke_pairing(): void
    {
        $featured = $this->tabletProduct();
        $recommendation = $this->tabletProduct();
        $tokenHash = Hash::make('tablet-pairing-token');
        $tablet = Tablet::create([
            'name' => 'Paired Tablet',
            'slug' => 'paired-tablet',
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('VISIBLE-01'),
            'activation_code_encrypted' => 'VISIBLE-01',
            'device_token_hash' => $tokenHash,
            'is_active' => true,
        ]);
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);

        $this->put(route('admin.tablets.update', $tablet), [
            'name' => 'Paired Tablet Updated',
            'slug' => 'paired-tablet',
            'featured_product_id' => $featured->id,
            'recommendation_ids' => [$recommendation->id],
            'activation_code' => 'VISIBLE-01',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertSame($tokenHash, $tablet->fresh()->device_token_hash);
    }

    public function test_interactive_table_update_increments_version(): void
    {
        $featured = $this->tabletProduct();
        $newFeatured = $this->tabletProduct();
        $rec = $this->tabletProduct();

        $tablet = Tablet::create([
            'name'                => 'Display Apparel',
            'slug'                => 'display-apparel',
            'featured_product_id' => $featured->id,
            'activation_code_hash'=> Hash::make('APP-01'),
            'config_version'      => 1,
            'is_active'           => true,
        ]);
        $tablet->recommendations()->attach($rec->id, ['sort_order' => 0]);

        $response = $this->put(route('admin.tablets.update', $tablet), [
            'name'                => 'Display Apparel Updated',
            'slug'                => 'display-apparel',
            'location'            => 'Lantai 2',
            'featured_product_id' => $newFeatured->id,
            'recommendation_ids'  => [$rec->id],
            'is_active'           => 1,
        ]);

        $response->assertRedirect(route('admin.tablets.edit', $tablet));
        $tablet->refresh();

        $this->assertEquals(2, $tablet->config_version);
        $this->assertEquals($newFeatured->id, $tablet->featured_product_id);
        $this->assertEquals('Display Apparel Updated', $tablet->name);
    }
}
