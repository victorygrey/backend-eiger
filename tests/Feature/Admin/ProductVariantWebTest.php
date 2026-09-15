<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_can_remove_all_variants_and_clear_parent_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $product->variants()->create([
            'sku' => '910012408001', 'name' => 'Black M', 'stock' => 5, 'price' => 100,
        ]);

        $this->put(route('admin.products.update', $product), [
            'name' => $product->name, 'variants_submitted' => 1,
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseMissing('product_variants', ['sku' => '910012408001']);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_edit_keeps_existing_variant_image_when_image_field_is_blank(): void
    {
        $product = Product::factory()->create(['image' => '/api/pim-media/parent.jpg']);
        $product->variants()->create([
            'sku' => '910012408001', 'name' => 'Black M', 'stock' => 2,
            'price' => 100, 'image' => '/api/pim-media/variant.jpg',
        ]);

        $this->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'variants_submitted' => 1,
            'variants' => [['sku' => '910012408001', 'stock' => 0, 'image' => '']],
        ])->assertRedirect(route('admin.products.index'));

        $this->assertDatabaseHas('product_variants', [
            'sku' => '910012408001', 'stock' => 0,
            'image' => '/api/pim-media/variant.jpg',
        ]);
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_edit_rejects_variant_sku_owned_by_another_product_without_partial_save(): void
    {
        $owner = Product::factory()->create();
        $owner->variants()->create([
            'sku' => '910012408001', 'name' => 'Black M', 'stock' => 2, 'price' => 100,
        ]);
        $target = Product::factory()->create(['name' => 'Original']);

        $this->putJson(route('admin.products.update', $target), [
            'name' => 'Changed',
            'variants_submitted' => 1,
            'variants' => [['sku' => '910012408001', 'name' => 'Wrong owner']],
        ])->assertUnprocessable()->assertJsonValidationErrors('variants.0.sku');

        $this->assertSame('Original', $target->fresh()->name);
        $this->assertDatabaseHas('product_variants', [
            'sku' => '910012408001', 'product_id' => $owner->id,
        ]);
    }
}
