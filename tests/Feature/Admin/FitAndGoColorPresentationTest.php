<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoDevice;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\FitAndGoProductPresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FitAndGoColorPresentationTest extends TestCase
{
    use RefreshDatabase;

    private function colorProduct(): Product
    {
        $product = Product::factory()->create([
            'sku' => '910000777', 'category' => 'Hat', 'ai_fit_and_go_active' => true,
            'is_discontinued' => false, 'image' => '/api/pim-media/black.jpg',
            'description' => 'Deskripsi PIM.',
        ]);
        foreach ([['BLACK', 'M', 'black'], [' black ', 'L', 'black'], ['OLIVE', 'M', 'olive']] as $i => [$color, $size, $file]) {
            ProductVariant::create([
                'product_id' => $product->id, 'sku' => $product->sku.sprintf('%03d', $i + 1),
                'name' => $color.' '.$size, 'color' => $color, 'size' => $size,
                'image' => '/api/pim-media/'.$file.'.jpg',
            ]);
        }
        $black = $product->variants()->first();
        ProductMedia::create([
            'product_id' => $product->id, 'product_variant_id' => $black->id,
            'url' => '/api/pim-media/black-back.jpg', 'role' => 'gallery', 'media_type' => 'image',
        ]);
        ProductMedia::create([
            'product_id' => $product->id, 'sku' => $product->sku.'003',
            'url' => '/api/pim-media/olive-back.jpg', 'role' => 'gallery', 'media_type' => 'image',
        ]);

        return $product;
    }

    private function rows(Product $product): array
    {
        $service = app(FitAndGoProductPresentation::class);

        return [
            ['key' => $service->colorKey('BLACK'), 'image' => '/api/pim-media/black-back.jpg', 'description' => 'Topi hitam tampak belakang.',
                'photo_descriptions' => ['/api/pim-media/black.jpg' => 'Topi hitam tampak depan.']],
            ['key' => $service->colorKey('OLIVE'), 'image' => '/api/pim-media/olive.jpg', 'description' => 'Topi olive tampak depan.'],
        ];
    }

    public function test_popup_groups_sizes_by_color_and_uses_variant_owned_photos(): void
    {
        $product = $this->colorProduct();
        $response = $this->getJson(route('admin.fit-and-go.product-presentation.show', $product))
            ->assertOk()->assertJsonCount(2, 'colors')->assertJsonCount(2, 'colors.0.variant_skus')
            ->assertJsonPath('colors.0.name', 'BLACK')->assertJsonPath('colors.1.name', 'OLIVE')
            ->assertJsonPath('colors.0.description', '')->assertJsonPath('colors.1.description', '')
            ->assertJsonCount(2, 'colors.0.photos')->assertJsonCount(2, 'colors.1.photos');
        $this->assertEqualsCanonicalizing(['/api/pim-media/black.jpg', '/api/pim-media/black-back.jpg'], array_column($response->json('colors.0.photos'), 'value'));
        $this->assertEqualsCanonicalizing(['/api/pim-media/olive.jpg', '/api/pim-media/olive-back.jpg'], array_column($response->json('colors.1.photos'), 'value'));
    }

    public function test_color_and_photo_descriptions_persist_and_fit_api_uses_each_variant_color(): void
    {
        $product = $this->colorProduct();
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $this->putJson($url, ['colors' => $this->rows($product)])->assertOk()->assertJsonPath('configured', true);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('configured', true)
            ->assertJsonPath('colors.0.image', '/api/pim-media/black-back.jpg')
            ->assertJsonPath('colors.0.description', 'Topi hitam tampak belakang.')
            ->assertJsonPath('colors.1.description', 'Topi olive tampak depan.');
        $this->assertSame('Topi hitam tampak depan.', $response->json('colors.0.photo_descriptions')['/api/pim-media/black.jpg']);
        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.image', url('/api/pim-media/black-back.jpg'))
            ->assertJsonPath('data.variants.0.image', url('/api/pim-media/black-back.jpg'))
            ->assertJsonPath('data.variants.1.description', 'Topi hitam tampak belakang.')
            ->assertJsonPath('data.variants.2.image', url('/api/pim-media/olive.jpg'))
            ->assertJsonPath('data.variants.2.description', 'Topi olive tampak depan.');
        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.description', 'Deskripsi PIM.')
            ->assertJsonPath('data.variants.0.image', url('/api/pim-media/black.jpg'))
            ->assertJsonMissingPath('data.variants.0.description');
    }

    public function test_missing_duplicate_or_wrong_color_choices_are_rejected_without_partial_writes(): void
    {
        $product = $this->colorProduct();
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $rows = $this->rows($product);
        $this->putJson($url, ['colors' => [$rows[0]]])->assertUnprocessable()->assertJsonValidationErrors('colors');
        $this->putJson($url, ['colors' => [$rows[0], $rows[0]]])->assertUnprocessable()->assertJsonValidationErrors('colors.0.key');
        $bad = $rows;
        $bad[1]['image'] = $rows[0]['image'];
        $this->putJson($url, ['colors' => $bad])->assertUnprocessable()->assertJsonValidationErrors('colors.1.image');
        $bad = $rows;
        $bad[1]['description'] = '   ';
        $this->putJson($url, ['colors' => $bad])->assertUnprocessable()->assertJsonValidationErrors('colors.1.description');
        $bad = $rows;
        $bad[0]['photo_descriptions']['/api/pim-media/olive.jpg'] = 'Salah warna.';
        $this->putJson($url, ['colors' => $bad])->assertUnprocessable()->assertJsonValidationErrors('colors.0.photo_descriptions');
        $this->assertDatabaseCount('fit_and_go_product_configurations', 0);
        $this->putJson($url, ['colors' => $rows])->assertOk();
        $this->putJson($url, ['colors' => $bad])->assertUnprocessable();
        $this->getJson($url)->assertJsonPath('colors.1.description', $rows[1]['description']);
    }

    public function test_legacy_product_wide_configuration_does_not_silently_configure_multiple_colors(): void
    {
        $product = $this->colorProduct();
        $product->fitAndGoConfiguration()->create(['image' => $product->image, 'description' => 'Konfigurasi lama.']);
        $this->getJson(route('admin.fit-and-go.product-presentation.show', $product))->assertOk()
            ->assertJsonPath('configured', false)->assertJsonPath('colors.0.description', '');
        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)->assertNotFound();
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $this->putJson(route('admin.fit-and-go.product-config.update', $device), [
            'selection_mode' => 'selected', 'product_ids' => [$product->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
    }

    public function test_new_color_or_removed_selected_photo_invalidates_readiness(): void
    {
        $product = $this->colorProduct();
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $this->putJson($url, ['colors' => $this->rows($product)])->assertOk();
        ProductVariant::create(['product_id' => $product->id, 'sku' => $product->sku.'004', 'name' => 'RED', 'color' => 'RED']);
        $this->getJson($url)->assertOk()->assertJsonPath('configured', false)->assertJsonPath('colors.2.description', '');
        $product->variants()->where('color', 'RED')->delete();
        ProductMedia::where('product_id', $product->id)->where('url', '/api/pim-media/black-back.jpg')->delete();
        $response = $this->getJson($url)->assertOk()->assertJsonPath('configured', false);
        $this->assertArrayNotHasKey('/api/pim-media/black-back.jpg', $response->json('colors.0.photo_descriptions'));
    }

    public function test_untagged_shared_photo_keeps_independent_descriptions_for_each_color(): void
    {
        $product = $this->colorProduct();
        ProductMedia::create([
            'product_id' => $product->id, 'url' => '/api/pim-media/shared.jpg',
            'role' => 'gallery', 'media_type' => 'image',
        ]);
        $rows = $this->rows($product);
        $rows[0]['image'] = $rows[1]['image'] = '/api/pim-media/shared.jpg';
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $this->putJson($url, ['colors' => array_reverse($rows)])->assertOk();
        $this->getJson($url)->assertOk()
            ->assertJsonPath('colors.0.description', $rows[0]['description'])
            ->assertJsonPath('colors.1.description', $rows[1]['description'])
            ->assertJsonPath('description', $rows[0]['description']);
        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.variants.0.description', $rows[0]['description'])
            ->assertJsonPath('data.variants.2.description', $rows[1]['description']);
    }
}
