<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Tablet;
use App\Models\TabletConfigVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TabletDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_tablet_can_activate_and_fetch_its_configured_catalog(): void
    {
        $featured = Product::factory()->create(['name' => 'Voyager Jacket 3.0']);
        $recommendation = Product::factory()->create(['name' => 'Greenland Pro']);
        $tablet = Tablet::create([
            'slug' => 'lobby-01',
            'name' => 'Lobby Tablet 01',
            'location' => 'Main Lobby',
            'featured_product_id' => $featured->id,
            'activation_code_hash' => Hash::make('LOBBY-01'),
        ]);
        $tablet->recommendations()->attach($recommendation->id, ['sort_order' => 0]);

        $token = $this->postJson('/api/tablets/activate', [
            'slug' => 'lobby-01',
            'activation_code' => 'LOBBY-01',
        ])->assertOk()->json('token');

        $this->withToken($token)
            ->getJson('/api/tablets/lobby-01/display')
            ->assertOk()
            ->assertJsonPath('tablet.slug', 'lobby-01')
            ->assertJsonPath('featured.name', 'Voyager Jacket 3.0')
            ->assertJsonPath('recommendations.0.name', 'Greenland Pro');
    }

    public function test_display_rejects_an_unpaired_device(): void
    {
        $product = Product::factory()->create();
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
        $product = Product::factory()->create();
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
        $oldFeatured = Product::factory()->create();
        $newFeatured = Product::factory()->create();
        $recommendation = Product::factory()->create();
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
        $featured = Product::factory()->create(['is_discontinued' => false]);
        $recommendation = Product::factory()->create(['is_discontinued' => false]);

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
}
