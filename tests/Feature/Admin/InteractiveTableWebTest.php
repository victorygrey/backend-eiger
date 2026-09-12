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

    public function test_interactive_table_index_loads_successfully(): void
    {
        $featured = Product::factory()->create([
            'name' => 'EIGER Streamline Daypack',
            'is_discontinued' => false,
        ]);
        $rec1 = Product::factory()->create(['name' => 'EIGER Hiking Cap', 'is_discontinued' => false]);
        $rec2 = Product::factory()->create(['name' => 'EIGER Windbreaker', 'is_discontinued' => false]);

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
        $response->assertSee('/tablet/meja-01');
        $response->assertSee('EIGER Streamline Daypack');
        $response->assertSee('2 produk');
        $response->assertSee('Online');
    }

    public function test_interactive_table_create_page_loads(): void
    {
        Product::factory()->count(3)->create(['is_discontinued' => false]);

        $response = $this->get(route('admin.tablets.create'));

        $response->assertStatus(200);
        $response->assertSee('Tambah Interactive Table');
        $response->assertSee('Produk Utama');
        $response->assertSee('Produk Rekomendasi');
        $response->assertSee('Aktivasi');
    }

    public function test_interactive_table_store_saves_ordered_recommendations(): void
    {
        $featured = Product::factory()->create(['is_discontinued' => false]);
        $rec1 = Product::factory()->create(['is_discontinued' => false]);
        $rec2 = Product::factory()->create(['is_discontinued' => false]);

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

        // Verify recommendation order preserved
        $recs = $tablet->recommendations()->orderBy('sort_order')->get();
        $this->assertCount(2, $recs);
        $this->assertEquals($rec2->id, $recs[0]->id);
        $this->assertEquals($rec1->id, $recs[1]->id);
    }

    public function test_interactive_table_edit_page_loads_with_modal_preview(): void
    {
        $featured = Product::factory()->create(['name' => 'EIGER Avalanche Jacket', 'is_discontinued' => false]);
        $rec = Product::factory()->create(['name' => 'EIGER Cargo Pants', 'is_discontinued' => false]);

        $tablet = Tablet::create([
            'name'                => 'Display Tengah',
            'slug'                => 'display-mid',
            'featured_product_id' => $featured->id,
            'activation_code_hash'=> Hash::make('MID-01'),
            'config_version'      => 1,
            'is_active'           => true,
        ]);
        $tablet->recommendations()->attach($rec->id, ['sort_order' => 0]);

        $response = $this->get(route('admin.tablets.edit', $tablet));

        $response->assertStatus(200);
        $response->assertSee('Display Tengah');
        $response->assertSee('EIGER Avalanche Jacket');
        $response->assertSee('EIGER Cargo Pants');
        $response->assertSee('Simulasi Tampilan Layar');
        $response->assertSee('Riwayat Versi Konfigurasi');
    }

    public function test_interactive_table_update_increments_version(): void
    {
        $featured = Product::factory()->create(['is_discontinued' => false]);
        $newFeatured = Product::factory()->create(['is_discontinued' => false]);
        $rec = Product::factory()->create(['is_discontinued' => false]);

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
