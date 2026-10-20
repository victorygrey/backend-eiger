<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\FitAndGoProductPresentation;
use App\Support\DeviceProductPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\ConfiguresFitAndGoProducts;
use Tests\TestCase;

class FitAndGoProductPresentationTest extends TestCase
{
    use ConfiguresFitAndGoProducts;
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => fake()->unique()->numerify('910######'),
            'image' => '/api/pim-media/cover.jpg',
            'description' => 'Deskripsi asli dari PIM.',
            'category' => 'Jacket', 'ai_fit_and_go_active' => true,
            'pim_catalog_active' => true, 'is_discontinued' => false,
        ], $overrides));
    }

    private function galleryPhoto(Product $product): string
    {
        $url = '/api/pim-media/gallery-'.$product->id.'.jpg';
        ProductMedia::create([
            'product_id' => $product->id, 'url' => $url,
            'role' => 'gallery', 'media_type' => 'image', 'mime_type' => 'image/jpeg',
        ]);

        return $url;
    }

    public function test_popup_starts_with_no_selected_photo_and_empty_description_and_deduplicates_gallery(): void
    {
        $product = $this->product();
        $photo = $this->galleryPhoto($product);
        ProductVariant::create(['product_id' => $product->id, 'sku' => $product->sku.'001', 'name' => 'Variant M', 'image' => 'https://old-cms.example'.$photo]);
        ProductMedia::create(['product_id' => $product->id, 'url' => '/api/pim-media/video.mp4', 'role' => 'gallery', 'media_type' => 'video']);

        $this->getJson(route('admin.fit-and-go.product-presentation.show', $product))
            ->assertOk()->assertJsonPath('image', null)->assertJsonPath('description', '')
            ->assertJsonPath('configured', false)->assertJsonCount(2, 'photos');
        $this->get(route('admin.fit-and-go.index'))->assertOk()->assertSee('Not Configure');
        $this->assertDatabaseCount('fit_and_go_product_configurations', 0);
    }

    public function test_both_photo_and_nonblank_description_are_required_and_foreign_photo_is_rejected(): void
    {
        $product = $this->product();
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $this->putJson($url, ['image' => $product->image, 'description' => '   '])->assertUnprocessable()->assertJsonValidationErrors('description');
        $this->putJson($url, ['description' => 'Jaket hiking.'])->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->putJson($url, ['image' => '/api/pim-media/another-product.jpg', 'description' => 'Jaket hiking.'])
            ->assertUnprocessable()->assertJsonValidationErrors('image');
        $this->assertDatabaseCount('fit_and_go_product_configurations', 0);
    }

    public function test_configuration_saves_without_editing_pim_and_can_be_reopened_and_updated(): void
    {
        $product = $this->product();
        $photo = $this->galleryPhoto($product);
        $url = route('admin.fit-and-go.product-presentation.update', $product);
        $this->putJson($url, ['image' => 'https://staging.example'.$photo, 'description' => '  Jaket pilihan AI.  '])
            ->assertOk()->assertJsonPath('configured', true);
        $this->assertSame('Deskripsi asli dari PIM.', $product->fresh()->description);
        $this->assertSame('/api/pim-media/cover.jpg', $product->fresh()->image);
        $this->getJson($url)->assertOk()->assertJsonPath('image', $photo)
            ->assertJsonPath('description', 'Jaket pilihan AI.')->assertJsonPath('configured', true);

        $this->putJson($url, ['image' => $product->image, 'description' => 'Deskripsi revisi.'])->assertOk();
        $this->assertDatabaseCount('fit_and_go_product_configurations', 1);
        $this->get(route('admin.fit-and-go.index'))->assertOk()->assertSee('Config Done');
    }

    public function test_product_cannot_be_selected_by_forged_request_until_configured(): void
    {
        $product = $this->product();
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $url = route('admin.fit-and-go.product-config.update', $device);
        $this->putJson($url, ['selection_mode' => 'selected', 'product_ids' => [$product->id]])
            ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->assertDatabaseMissing('fit_and_go_item_visibilities', ['product_id' => $product->id]);

        $this->putJson(route('admin.fit-and-go.product-presentation.update', $product), [
            'image' => $product->image, 'description' => 'Deskripsi untuk AI.',
        ])->assertOk();
        $this->put($url, ['selection_mode' => 'selected', 'product_ids' => [$product->id]])->assertRedirect();
        $this->assertDatabaseHas('fit_and_go_item_visibilities', ['product_id' => $product->id, 'device_id' => $device->id, 'is_visible' => true]);
    }

    public function test_fit_and_go_api_uses_configured_photo_and_description_but_other_payloads_keep_pim_data(): void
    {
        $product = $this->product();
        $photo = $this->galleryPhoto($product);
        ProductVariant::create(['product_id' => $product->id, 'sku' => $product->sku.'001', 'name' => 'Variant M', 'image' => null]);
        $this->putJson(route('admin.fit-and-go.product-presentation.update', $product), [
            'image' => $photo, 'description' => 'Jaket untuk visual AI.',
        ])->assertOk();

        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.image', url($photo))->assertJsonPath('data.description', 'Jaket untuk visual AI.')
            ->assertJsonPath('data.variants.0.image', url($photo));
        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.image', url('/api/pim-media/cover.jpg'))
            ->assertJsonPath('data.description', 'Deskripsi asli dari PIM.');
        $shared = DeviceProductPayload::make($product->fresh());
        $this->assertSame('Deskripsi asli dari PIM.', $shared['description']);
        $this->assertSame(url('/api/pim-media/cover.jpg'), $shared['image']);
    }

    public function test_unconfigured_products_are_excluded_from_every_fit_and_go_catalog_path(): void
    {
        $ready = $this->product(['name' => 'Ready Jacket']);
        $unready = $this->product(['name' => 'Unready Jacket']);
        $this->configureFitProduct($ready);
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $device->update(['device_token_hash' => Hash::make('test-token')]);
        FitAndGoCategory::updateOrCreate(['code' => 'apparel'], ['display_name' => 'Top', 'is_active' => true]);
        $activity = FitAndGoActivity::where('slug', 'hiking')->firstOrFail();
        $activity->recommendedProducts()->sync([$ready->id, $unready->id]);
        foreach ([$ready, $unready] as $product) {
            FitAndGoItemVisibility::create(['product_id' => $product->id, 'category_code' => 'apparel', 'is_visible' => true]);
        }
        foreach ([
            '/api/v1/fit-and-go/products',
            '/api/v1/fit-and-go/search?q=Jacket',
            '/api/v1/fit-and-go/activities/hiking/recommendations',
        ] as $path) {
            $this->getJson($path)->assertOk()->assertJsonPath('count', 1)
                ->assertJsonFragment(['sku' => $ready->sku])->assertJsonMissing(['sku' => $unready->sku]);
        }
        $this->withToken('test-token')->getJson('/api/v1/fit-and-go/kiosks/fit-and-go-lantai-1')
            ->assertOk()->assertJsonFragment(['sku' => $ready->sku])->assertJsonMissing(['sku' => $unready->sku]);
        $this->getJson('/api/v1/fit-and-go/activities')->assertOk()->assertJsonMissing(['sku' => $unready->sku]);
        $this->getJson('/api/v1/fit-and-go/products/'.$unready->id)->assertNotFound();
    }

    public function test_latest_mode_excludes_unconfigured_selected_products(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $device->update(['product_selection_mode' => 'latest']);
        $ready = $this->product();
        $this->configureFitProduct($ready);
        FitAndGoItemVisibility::create([
            'device_id' => $device->id, 'product_id' => $ready->id,
            'category_code' => 'apparel', 'is_visible' => true,
        ]);
        $ready->update(['updated_at' => now()->subDays(2)]);
        for ($index = 0; $index < 31; $index++) {
            $this->product(['updated_at' => now()]);
        }
        $this->getJson('/api/v1/fit-and-go/products?device_code=fit-and-go-lantai-1&limit=1')
            ->assertOk()->assertJsonPath('count', 1)->assertJsonPath('data.0.id', $ready->id);
        $this->get(route('admin.fit-and-go.index', ['device_id' => $device->id]))
            ->assertOk()->assertSee('1 produk dikonfigurasi');
    }

    public function test_removed_photo_and_inactive_channel_make_configuration_unavailable(): void
    {
        $product = $this->product();
        $this->configureFitProduct($product);
        $product->update(['image' => '/api/pim-media/new-cover.jpg']);
        $this->assertFalse(app(FitAndGoProductPresentation::class)->isConfigured($product->fresh()));
        $this->getJson('/api/v1/fit-and-go/products/'.$product->id)->assertNotFound();
        $product->update(['ai_fit_and_go_active' => false]);
        $this->getJson(route('admin.fit-and-go.product-presentation.show', $product))->assertNotFound();
        $this->putJson(route('admin.fit-and-go.product-presentation.update', $product), [
            'image' => $product->image, 'description' => 'Deskripsi.',
        ])->assertNotFound();
    }
}
