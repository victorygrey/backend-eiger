<?php

namespace Tests\Feature\Admin;

use App\Models\AtomProductCategory;
use App\Models\AtomProductSubCategory;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionItem;
use App\Services\TableExpeditionProductType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableExpeditionComparisonCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => fake()->unique()->numerify('910######'),
            'category' => 'Sepatu', 'is_discontinued' => false,
            'pim_catalog_active' => true,
        ], $overrides));
    }

    private function primary(Product $product): TableExpeditionItem
    {
        RfidTag::create(['uid' => 'AABBCCDD', 'product_id' => $product->id]);

        return TableExpeditionItem::create(['rfid_tag' => 'AABBCCDD', 'product_id' => $product->id, 'is_active' => true]);
    }

    public function test_catalog_includes_all_similar_products_even_without_active_rfid(): void
    {
        $primary = $this->product(['name' => 'Sepatu Utama']);
        $this->primary($primary);
        $shoes = collect(range(1, 7))->map(fn ($i) => $this->product(['name' => 'Sepatu '.$i, 'stock' => 0]));
        $inactiveTagProduct = $shoes->first();
        RfidTag::create(['uid' => '11223344', 'product_id' => $inactiveTagProduct->id]);
        TableExpeditionItem::create(['rfid_tag' => '11223344', 'product_id' => $inactiveTagProduct->id, 'is_active' => false]);
        $sandals = $this->product(['category' => 'Sandal']);
        $jacket = $this->product(['category' => 'Jaket']);
        $discontinued = $this->product(['is_discontinued' => true]);
        $this->get(route('admin.table-expedition.index'))->assertOk()
            ->assertViewHas('comparisonProducts', function ($products) use ($primary, $shoes, $sandals, $jacket, $discontinued): bool {
                $ids = array_column($products, 'id');
                $this->assertCount(8, $ids);
                $this->assertContains($primary->id, $ids);
                foreach ($shoes as $shoe) {
                    $this->assertContains($shoe->id, $ids);
                }
                foreach ([$sandals, $jacket, $discontinued] as $excluded) {
                    $this->assertNotContains($excluded->id, $ids);
                }

                return true;
            });
    }

    public function test_admin_selects_unmapped_catalog_products_and_scan_and_compare_keep_saved_order(): void
    {
        $primary = $this->product();
        $item = $this->primary($primary);
        $first = $this->product(['name' => 'Sepatu Pertama']);
        $second = $this->product(['name' => 'Sepatu Kedua', 'category' => 'Shoes']);
        $ids = [$second->id, $first->id];
        $this->put(route('admin.table-expedition.comparisons.update', $item), ['similar_product_ids' => $ids])
            ->assertRedirect(route('admin.table-expedition.index'))->assertSessionHasNoErrors();
        $this->assertSame($ids, $item->fresh()->similar_product_ids);
        $response = $this->postJson('/api/v1/table-expedition/scan', ['rfid_tag' => 'AABBCCDD'])->assertOk();
        $this->assertSame($ids, array_column($response->json('data.comparison_products'), 'id'));
        $this->postJson('/api/v1/table-expedition/compare', ['product_id_1' => $primary->id, 'product_id_2' => $second->id])
            ->assertOk()->assertJsonPath('data.secondary.product.id', $second->id)->assertJsonPath('data.secondary.rfid_tag', null);
        $this->assertDatabaseCount('rfid_tags', 1);
        $this->assertDatabaseCount('table_expedition_items', 1);
    }

    public function test_mismatched_same_product_and_more_than_five_choices_are_rejected(): void
    {
        $primary = $this->product();
        $item = $this->primary($primary);
        $foreign = $this->product(['category' => 'Jacket']);
        $url = route('admin.table-expedition.comparisons.update', $item);
        $this->putJson($url, ['similar_product_ids' => [$foreign->id]])->assertUnprocessable()->assertJsonValidationErrors('similar_product_ids');
        $this->putJson($url, ['similar_product_ids' => [$primary->id]])->assertUnprocessable();
        $six = collect(range(1, 6))->map(fn () => $this->product());
        $this->putJson($url, ['similar_product_ids' => $six->pluck('id')->all()])->assertUnprocessable();
        $this->putJson($url, ['similar_product_ids' => $six->take(5)->pluck('id')->all()])->assertRedirect();
        $this->postJson('/api/v1/table-expedition/compare', ['product_id_1' => $primary->id, 'product_id_2' => $six->last()->id])
            ->assertUnprocessable();
        // Old or forged saved IDs must not bypass the same-kind rule at runtime.
        $item->update(['similar_product_ids' => [$foreign->id]]);
        $this->postJson('/api/v1/table-expedition/scan', ['rfid_tag' => 'AABBCCDD'])
            ->assertOk()->assertJsonCount(0, 'data.comparison_products');
        $this->postJson('/api/v1/table-expedition/compare', ['product_id_1' => $primary->id, 'product_id_2' => $foreign->id])
            ->assertUnprocessable();
    }

    public function test_master_subcategory_takes_precedence_and_groups_shoes_and_boots(): void
    {
        $category = AtomProductCategory::create(['source_id' => 9991, 'external_id' => 'TEST-F', 'name' => 'Footwear', 'slug' => 'test-footwear']);
        $shoeSub = AtomProductSubCategory::create(['source_id' => 9992, 'category_id' => $category->id, 'external_id' => 'TEST-F1', 'name' => 'Sepatu', 'slug' => 'test-shoes']);
        $bootSub = AtomProductSubCategory::create(['source_id' => 9993, 'category_id' => $category->id, 'external_id' => 'TEST-F2', 'name' => 'Sepatu Boots', 'slug' => 'test-boots']);
        $primary = $this->product(['category' => 'Jaket', 'name' => 'Nama acak', 'atom_product_category_id' => $category->id, 'atom_product_sub_category_id' => $shoeSub->id]);
        $boots = $this->product(['atom_product_category_id' => $category->id, 'atom_product_sub_category_id' => $bootSub->id]);
        $misleadingName = $this->product(['category' => 'Sandal', 'name' => 'Sepatu Hiking']);
        $types = app(TableExpeditionProductType::class);
        $this->assertTrue($types->matches($primary, $boots));
        $this->assertFalse($types->matches($primary, $misleadingName));
        $this->assertSame('Sepatu', $types->resolve($primary)['label']);
    }

    public function test_comparisons_disappear_when_discontinued_or_removed_from_pim_catalog(): void
    {
        $primary = $this->product();
        $item = $this->primary($primary);
        $comparison = $this->product();
        $item->update(['similar_product_ids' => [$comparison->id]]);
        $comparison->pimRecord()->create([
            'generic_sku' => $comparison->sku, 'product_payload' => ['generic' => $comparison->sku],
            'payload_checksum' => hash('sha256', $comparison->sku),
        ]);
        $comparison->update(['pim_catalog_active' => false]);
        $this->postJson('/api/v1/table-expedition/scan', ['rfid_tag' => 'AABBCCDD'])->assertOk()->assertJsonCount(0, 'data.comparison_products');
        $comparison->update(['pim_catalog_active' => true, 'is_discontinued' => true]);
        $this->postJson('/api/v1/table-expedition/compare', ['product_id_1' => $primary->id, 'product_id_2' => $comparison->id])
            ->assertUnprocessable();
        $this->putJson(route('admin.table-expedition.comparisons.update', $item), ['similar_product_ids' => [$comparison->id]])
            ->assertUnprocessable();
    }
}
