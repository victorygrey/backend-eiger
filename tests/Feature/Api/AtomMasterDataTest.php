<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\PimProductDataStore;
use Database\Seeders\AtomMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtomMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_snapshot_is_imported_and_exposed_hierarchically(): void
    {
        $this->seed(AtomMasterDataSeeder::class);

        $this->assertDatabaseCount('atom_product_categories', 8);
        $this->assertDatabaseCount('atom_product_sub_categories', 92);
        $this->assertDatabaseCount('atom_product_activity_groups', 9);
        $this->assertDatabaseCount('atom_product_activities', 20);
        $this->getJson('/api/master-data/categories')->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonFragment(['external_id' => 'F', 'name' => 'Footwear'])
            ->assertJsonFragment(['external_id' => 'F01', 'name' => 'Sepatu']);
        $this->getJson('/api/master-data/activities')->assertOk()
            ->assertJsonFragment(['external_id' => 'A03018', 'name' => 'Hiking']);
    }

    public function test_pim_values_are_linked_to_atom_master_rows(): void
    {
        $this->seed(AtomMasterDataSeeder::class);
        $product = Product::factory()->create(['sku' => '910000001']);
        app(PimProductDataStore::class)->replace($product, [
            'generic' => '910000001', 'name' => 'Trail Shoe', 'variant' => [],
            'customAtributes' => [
                ['attributeCode' => 'category', 'value' => 'Shoes'],
                ['attributeCode' => 'activity', 'value' => 'Hiking'],
            ],
        ]);

        $product->refresh();
        $this->assertSame('Footwear', $product->atomCategory->name);
        $this->assertSame('Sepatu', $product->atomSubCategory->name);
        $this->assertDatabaseHas('product_activities', [
            'product_id' => $product->id,
            'pim_id' => 'A03018',
            'name' => 'Hiking',
        ]);
    }
}
