<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tablet;
use App\Models\TabletConfigVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TabletDisplayTest extends TestCase
{
    use RefreshDatabase;

    private int $tabletSkuSequence = 910800000;

    private function tabletProduct(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'sku' => (string) ++$this->tabletSkuSequence,
            'interactive_tablet_active' => true,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ], $attributes));
    }

    public function test_tablet_can_activate_and_fetch_its_configured_catalog(): void
    {
        $featured = $this->tabletProduct([
            'name' => 'Voyager Jacket 3.0',
            'image' => '/api/pim-media/voyager-cover.jpg',
        ]);
        $recommendation = $this->tabletProduct(['name' => 'Greenland Pro']);
        ProductVariant::create([
            'product_id' => $featured->id,
            'sku' => 'VOYAGER-BLK-M',
            'name' => 'Voyager Black M',
            'color' => 'Black',
            'size' => 'M',
            'price' => 899000,
            'stock' => 4,
        ]);
        $tablet = Tablet::create([
            'slug' => 'lobby-01',
            'name' => 'Lobby Tablet 01',
            'location' => 'Main Lobby',
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('LOBBY-01'),
        ]);
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);

        $token = $this->postJson('/api/tablets/activate', [
            'activation_code' => 'LOBBY-01',
        ])->assertOk()->json('token');

        $this->assertNotNull($tablet->fresh()->activation_code_lookup_hash);

        $this->withToken($token)
            ->getJson('/api/tablets/lobby-01/display')
            ->assertOk()
            ->assertJsonPath('tablet.slug', 'lobby-01')
            ->assertJsonPath('featured.name', 'Voyager Jacket 3.0')
            ->assertJsonPath('featured.variants.0.sku', 'VOYAGER-BLK-M')
            ->assertJsonPath('featured.variants.0.image', url('/api/pim-media/voyager-cover.jpg'))
            ->assertJsonPath('featured.variants.0.size', 'M')
            ->assertJsonMissingPath('featured.available_sizes')
            ->assertJsonMissingPath('featured.available_colors')
            ->assertJsonMissingPath('featured.imageUrl')
            ->assertJsonMissingPath('featured.features')
            ->assertJsonMissingPath('featured.materials')
            ->assertJsonMissingPath('featured.activity')
            ->assertJsonPath('recommendations.0.name', 'Greenland Pro');
    }

    public function test_display_rejects_an_unpaired_device(): void
    {
        $product = $this->tabletProduct();
        Tablet::create([
            'slug' => 'lobby-01',
            'name' => 'Lobby Tablet 01',
            'featured_product_id' => $product->id,
            'activation_code_hash' => Hash::make('LOBBY-01'),
        ]);

        $this->getJson('/api/tablets/lobby-01/display')->assertUnauthorized();
    }

    public function test_heartbeat_updates_device_health(): void
    {
        $product = $this->tabletProduct();
        $token = 'device-token';
        $tablet = Tablet::create([
            'slug' => 'lobby-01',
            'name' => 'Lobby Tablet 01',
            'featured_product_id' => $product->id,
            'activation_code_hash' => Hash::make('LOBBY-01'),
            'device_token_hash' => Hash::make($token),
        ]);

        $this->withToken($token)
            ->postJson('/api/tablets/lobby-01/heartbeat', ['version' => 1, 'media_status' => 'ready'])
            ->assertOk()
            ->assertJsonPath('refresh_required', false);

        $tablet->refresh();
        $this->assertNotNull($tablet->last_seen_at);
        $this->assertSame('ready', $tablet->media_status);
    }

    public function test_admin_can_rollback_to_an_older_configuration_as_a_new_version(): void
    {
        $oldFeatured = $this->tabletProduct();
        $newFeatured = $this->tabletProduct();
        $recommendation = $this->tabletProduct();
        $tablet = Tablet::create([
            'slug' => 'lobby-01',
            'name' => 'Lobby Tablet 01',
            'featured_product_id' => $newFeatured->id,
            'activation_code_hash' => Hash::make('LOBBY-01'),
            'config_version' => 2,
        ]);
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);
        $version = TabletConfigVersion::create([
            'tablet_id' => $tablet->id,
            'version_number' => 1,
            'featured_product_id' => $oldFeatured->id,
            'recommendation_product_ids' => [$recommendation->id],
        ]);

        $this->post(route('admin.tablets.rollback', [$tablet, $version]))->assertRedirect();

        $tablet->refresh();
        $this->assertSame(3, $tablet->config_version);
        $this->assertSame($oldFeatured->id, $tablet->featured_product_id);
        $this->assertDatabaseHas('tablet_config_versions', [
            'tablet_id' => $tablet->id,
            'version_number' => 3,
        ]);
    }

    public function test_admin_can_save_valid_product_ids_from_the_tablet_form(): void
    {
        $featured = $this->tabletProduct();
        $recommendation = $this->tabletProduct();

        $this->post(route('admin.tablets.store'), [
            'name' => 'Lobby Tablet 01',
            'slug' => 'lobby-01',
            'location' => 'Main Lobby',
            'featured_product_id' => (string) $featured->id,
            'recommendation_ids' => [(string) $recommendation->id],
            'activation_code' => 'LOBBY-01',
            'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tablets', [
            'slug' => 'lobby-01',
            'featured_product_id' => $featured->id,
        ]);
        $this->assertDatabaseHas('tablet_recommendations', [
            'product_id' => $recommendation->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('tablet_config_versions', [
            'version_number' => 1,
            'featured_product_id' => $featured->id,
        ]);
    }

    public function test_display_stops_exposing_products_after_tablet_status_is_unchecked(): void
    {
        $featured = $this->tabletProduct(['name' => 'Featured Active']);
        $recommendation = $this->tabletProduct(['name' => 'Recommendation Active']);
        $token = 'tablet-token';
        $tablet = Tablet::create([
            'slug' => 'channel-controlled',
            'name' => 'Channel Controlled Tablet',
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('CHANNEL-01'),
            'device_token_hash' => Hash::make($token),
        ]);
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);

        $recommendation->update(['interactive_tablet_active' => false]);

        $this->withToken($token)
            ->getJson('/api/tablets/channel-controlled/display')
            ->assertOk()
            ->assertJsonCount(0, 'recommendations');

        $featured->update(['interactive_tablet_active' => false]);

        $this->withToken($token)
            ->getJson('/api/tablets/channel-controlled/display')
            ->assertNotFound()
            ->assertJsonPath('message', 'Produk utama tablet sudah tidak aktif pada List Product.');
    }
}
