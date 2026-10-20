<?php

namespace Tests\Feature\Admin;

use App\Models\FitAndGoActivity;
use App\Models\FitAndGoCategory;
use App\Models\FitAndGoDevice;
use App\Models\FitAndGoItemVisibility;
use App\Models\Product;
use App\Services\FitAndGoProductClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ConfiguresFitAndGoProducts;
use Tests\TestCase;

class FitAndGoUnlimitedSelectionTest extends TestCase
{
    use ConfiguresFitAndGoProducts;
    use RefreshDatabase;

    public function test_both_modes_save_more_than_thirty_products_and_latest_returns_every_selected_type(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $device->update(['activation_code_hash' => null]);
        $classifier = app(FitAndGoProductClassifier::class);
        foreach ($classifier->definitions() as $code => $definition) {
            FitAndGoCategory::updateOrCreate(['code' => $code], [
                'display_name' => $definition['label'], 'is_active' => true,
            ]);
        }
        $products = collect();
        // A large Top group must not crowd out the other four groups.
        foreach (['Jacket' => 51, 'Hat' => 2, 'Pants' => 2, 'Bag' => 2, 'Shoes' => 2] as $category => $count) {
            for ($index = 0; $index < $count; $index++) {
                $product = Product::factory()->create([
                    'sku' => (string) (910100000 + $products->count()),
                    'name' => "Selection {$category} {$index}", 'category' => $category,
                    'created_at' => now()->subDays($index + 1),
                    'pim_catalog_active' => true, 'is_discontinued' => false,
                ]);
                $this->configureFitProduct($product);
                $products->push($product);
            }
        }
        foreach (['selected', 'latest'] as $mode) {
            $this->put(route('admin.fit-and-go.product-config.update', $device), [
                'selection_mode' => $mode, 'selection_submitted' => 1,
                'product_ids' => $products->pluck('id')->all(),
            ])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame(59, $device->itemVisibilities()->where('is_visible', true)->count());
        }

        $outsider = Product::factory()->create([
            'sku' => '910999999', 'name' => 'Selection Unselected Hat', 'category' => 'Hat',
            'pim_catalog_active' => true, 'is_discontinued' => false,
        ]);
        $this->configureFitProduct($outsider);
        FitAndGoItemVisibility::create([
            'product_id' => $outsider->id, 'category_code' => 'hat', 'is_visible' => true,
        ]);
        FitAndGoItemVisibility::create([
            'device_id' => FitAndGoDevice::where('device_code', 'fit-and-go-lantai-2')->value('id'),
            'product_id' => $outsider->id, 'category_code' => 'hat', 'is_visible' => true,
        ]);
        $expected = $products->sortBy([['created_at', 'desc'], ['id', 'desc']])->pluck('id')->all();
        foreach (['products?', 'search?q=Selection&'] as $path) {
            $response = $this->getJson('/api/v1/fit-and-go/'.$path.'device_code='.$device->device_code)
                ->assertOk()->assertJsonPath('count', 59)->assertJsonMissing(['sku' => $outsider->sku]);
            $this->assertSame($expected, array_column($response->json('data'), 'id'));
        }
        $this->getJson('/api/v1/fit-and-go/products?device_code='.$device->device_code.'&limit=3')
            ->assertOk()->assertJsonCount(3, 'data');

        $response = $this->getJson('/api/v1/fit-and-go/kiosks/'.$device->device_code)->assertOk();
        foreach (array_keys($classifier->definitions()) as $code) {
            $group = collect($response->json('data.categories'))->firstWhere('code', $code);
            $expectedGroup = $products->filter(fn ($product) => $classifier->groupCode($product) === $code)
                ->sortBy([['created_at', 'desc'], ['id', 'desc']])->pluck('id')->all();
            $this->assertSame($expectedGroup, array_column($group['shown_items'], 'id'));
        }
        $activity = FitAndGoActivity::where('slug', 'hiking')->firstOrFail();
        foreach ([$outsider, $products->first()] as $position => $product) {
            DB::table('fit_and_go_device_activity_products')->insert([
                'device_id' => $device->id, 'activity_id' => $activity->id,
                'product_id' => $product->id, 'sort_order' => $position,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['products?activity=hiking&', 'activities/hiking/recommendations?'] as $path) {
            $this->getJson('/api/v1/fit-and-go/'.$path.'device_code='.$device->device_code)
                ->assertOk()->assertJsonPath('count', 1)
                ->assertJsonPath('data.0.id', $products->first()->id);
        }
        $this->get(route('admin.fit-and-go.index', ['device_id' => $device->id]))
            ->assertOk()->assertSee('59 produk dikonfigurasi')->assertDontSee('maksimal 30');
    }

    public function test_clearing_latest_selection_does_not_restore_global_or_previous_products(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $product = Product::factory()->create(['sku' => '910111222', 'category' => 'Hat', 'is_discontinued' => false]);
        $this->configureFitProduct($product);
        FitAndGoItemVisibility::create([
            'device_id' => $device->id, 'product_id' => $product->id,
            'category_code' => 'hat', 'is_visible' => true,
        ]);
        $this->put(route('admin.fit-and-go.product-config.update', $device), [
            'selection_mode' => 'latest', 'selection_submitted' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(0, $device->itemVisibilities()->count());
        $this->getJson('/api/v1/fit-and-go/products?device_code='.$device->device_code)
            ->assertOk()->assertJsonPath('count', 0);
    }

    public function test_latest_still_requires_configured_products_and_screen_uses_creation_order(): void
    {
        $device = FitAndGoDevice::where('device_code', 'fit-and-go-lantai-1')->firstOrFail();
        $newer = Product::factory()->create([
            'sku' => '910111333', 'name' => 'New Arrival Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => true, 'created_at' => now()->subDay(),
            'is_discontinued' => false,
        ]);
        $older = Product::factory()->create([
            'sku' => '910111444', 'name' => 'Old Arrival Jacket', 'category' => 'Jacket',
            'ai_fit_and_go_active' => true, 'created_at' => now()->subMonth(),
            'updated_at' => now()->addDay(),
            'is_discontinued' => false,
        ]);
        $this->putJson(route('admin.fit-and-go.product-config.update', $device), [
            'selection_mode' => 'latest', 'product_ids' => [$newer->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $this->get(route('admin.fit-and-go.index', ['device_id' => $device->id]))
            ->assertOk()->assertSeeInOrder([$newer->name, $older->name])
            ->assertSee('fit-save-bar')->assertSee('selection_submitted');
    }
}
