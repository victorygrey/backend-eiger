<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Database\Seeders\AtomMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_detail_displays_variants_without_edit_controls(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $product->variants()->create([
            'sku' => '910012408001', 'name' => 'Black M', 'stock' => 5, 'price' => 100,
        ]);

        $this->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Detail Produk')
            ->assertSee('Detail ini hanya dapat dibaca')
            ->assertSee('910012408001')
            ->assertSee('Black M')
            ->assertDontSee('Simpan Perubahan')
            ->assertDontSee('Tarik Data PIM &amp; CARE', false);
    }

    public function test_manual_product_edit_routes_are_not_available(): void
    {
        $product = Product::factory()->create(['name' => 'PIM Product']);

        $this->get('/admin/products/'.$product->id.'/edit')->assertNotFound();
        $this->put('/admin/products/'.$product->id, ['name' => 'Changed'])->assertStatus(405);
        $this->putJson('/api/products/'.$product->id, ['name' => 'Changed'])->assertStatus(405);
        $this->assertSame('PIM Product', $product->fresh()->name);
    }

    public function test_product_can_still_be_deleted_from_cms(): void
    {
        $product = Product::factory()->create(['name' => 'Removable Product']);

        $this->delete(route('admin.products.destroy', $product))
            ->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    public function test_detail_translates_pim_codes_with_database_master_data(): void
    {
        $this->seed(AtomMasterDataSeeder::class);
        $product = Product::factory()->create();
        $product->customAttributesRelation()->create([
            'attribute_code' => 'activity',
            'value' => 'A03018',
            'value_type' => 'string',
            'sort_order' => 0,
        ]);

        $this->get(route('admin.products.show', $product))
            ->assertOk()
            ->assertSee('Hiking')
            ->assertSee('Activity · Camping &amp; Hiking', false)
            ->assertSee('A03018');
    }
}
