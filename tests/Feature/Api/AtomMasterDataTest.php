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

    public function test_official_codes_are_resolved_to_canonical_master_meanings(): void
    {
        $this->seed(AtomMasterDataSeeder::class);
        $product = Product::factory()->create(['sku' => '910000002']);

        app(PimProductDataStore::class)->replace($product, [
            'generic' => '910000002', 'name' => 'Coded Trail Shoe', 'variant' => [],
            'customAtributes' => [
                ['attributeCode' => 'categoryCode', 'value' => 'F'],
                ['attributeCode' => 'subCategoryCode', 'value' => 'F01'],
                ['attributeCode' => 'activity', 'value' => 'A03018'],
            ],
            'activity' => [
                ['external_id' => 'A03001', 'selected' => 'true'],
            ],
        ]);

        $product->refresh()->load([
            'atomCategory', 'atomSubCategory', 'activitiesRelation.atomActivity.group',
        ]);

        $this->assertSame('Sepatu', $product->category);
        $this->assertSame('F', $product->atomCategory->external_id);
        $this->assertSame('Footwear', $product->atomCategory->name);
        $this->assertSame('F01', $product->atomSubCategory->external_id);
        $this->assertSame('Sepatu', $product->atomSubCategory->name);
        $this->assertDatabaseHas('product_custom_attributes', [
            'product_id' => $product->id, 'attribute_code' => 'categoryCode', 'value' => 'F',
        ]);
        $this->assertDatabaseHas('product_activities', [
            'product_id' => $product->id, 'pim_id' => 'A03001', 'name' => 'Day Hike',
        ]);
        $this->assertDatabaseHas('product_activities', [
            'product_id' => $product->id, 'pim_id' => 'A03018', 'name' => 'Hiking',
        ]);

        $activities = collect($product->activities);
        $dayHike = $activities->firstWhere('master_code', 'A03001');
        $this->assertSame('Day Hike', $dayHike['name']);
        $this->assertSame('Camping & Hiking', $dayHike['master_activity']['group']['name']);
    }
}
