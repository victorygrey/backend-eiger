<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductChannelWebTest extends TestCase
{
    use RefreshDatabase;

    protected bool $autoAuthenticate = false;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'superadmin',
            'is_active' => true,
        ]);
    }

    public function test_product_list_shows_independent_channel_controls(): void
    {
        Product::factory()->create([
            'sku' => '910900001',
            'name' => 'AI ONLY PRODUCT',
            'ai_fit_and_go_active' => true,
            'interactive_tablet_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('AI Product')
            ->assertSee('Tablet')
            ->assertSee('AI ONLY PRODUCT')
            ->assertSee('Detail')
            ->assertSee('Hapus produk')
            ->assertDontSee('Tambah Produk')
            ->assertDontSee('Edit')
            ->assertSee('Pengaturan kanal terkunci')
            ->assertSee('Unlock Pengaturan')
            ->assertSee('channel-status-toggle')
            ->assertSee('disabled', false)
            ->assertSee('ai_fit_and_go_active')
            ->assertSee('interactive_tablet_active');
    }

    public function test_each_channel_can_be_changed_without_affecting_the_other(): void
    {
        $product = Product::factory()->create([
            'sku' => '910900002',
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['products.channel_settings_unlocked' => true])
            ->patch(route('admin.products.channel.update', $product), [
                'channel' => 'ai_fit_and_go_active',
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'ai_fit_and_go_active' => true,
            'interactive_tablet_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['products.channel_settings_unlocked' => true])
            ->patch(route('admin.products.channel.update', $product), [
                'channel' => 'interactive_tablet_active',
                'active' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'ai_fit_and_go_active' => true,
            'interactive_tablet_active' => false,
        ]);
    }

    public function test_locked_channel_settings_reject_changes_until_user_unlocks_them(): void
    {
        $product = Product::factory()->create([
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => false,
        ]);

        $this->actingAs($this->admin)
            ->patch(route('admin.products.channel.update', $product), [
                'channel' => 'ai_fit_and_go_active',
                'active' => true,
            ])->assertStatus(423);

        $this->assertFalse($product->fresh()->ai_fit_and_go_active);

        $this->patch(route('admin.products.channel-lock.update'), ['unlocked' => true])
            ->assertRedirect()
            ->assertSessionHas('products.channel_settings_unlocked', true);

        $this->patch(route('admin.products.channel.update', $product), [
            'channel' => 'ai_fit_and_go_active',
            'active' => true,
        ])->assertRedirect();

        $this->assertTrue($product->fresh()->ai_fit_and_go_active);
    }

    public function test_channel_status_filters_are_independent(): void
    {
        Product::factory()->create([
            'sku' => '910900003',
            'name' => 'AI ACTIVE',
            'ai_fit_and_go_active' => true,
            'interactive_tablet_active' => false,
        ]);
        Product::factory()->create([
            'sku' => '910900004',
            'name' => 'TABLET ACTIVE',
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['ai_status' => '1']))
            ->assertOk()
            ->assertSee('AI ACTIVE')
            ->assertDontSee('TABLET ACTIVE');

        $this->actingAs($this->admin)
            ->get(route('admin.products.index', ['tablet_status' => '1']))
            ->assertOk()
            ->assertSee('TABLET ACTIVE')
            ->assertDontSee('AI ACTIVE');
    }

    public function test_existing_device_mappings_are_backfilled_into_channel_statuses(): void
    {
        $aiProduct = Product::factory()->create([
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => false,
        ]);
        $tabletFeatured = Product::factory()->create([
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => false,
        ]);
        $tabletRecommendation = Product::factory()->create([
            'ai_fit_and_go_active' => false,
            'interactive_tablet_active' => false,
        ]);

        $deviceId = DB::table('fit_and_go_devices')->insertGetId([
            'name' => 'Legacy Kiosk',
            'device_code' => 'legacy-kiosk',
            'status' => 'offline',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('fit_and_go_item_visibilities')->insert([
            'device_id' => $deviceId,
            'product_id' => $aiProduct->id,
            'category_code' => 'top',
            'is_visible' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tabletId = DB::table('tablets')->insertGetId([
            'name' => 'Legacy Tablet',
            'slug' => 'legacy-tablet',
            'featured_product_id' => $tabletFeatured->id,
            'activation_code_hash' => bcrypt('TABLET-01'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('tablet_recommendations')->insert([
            'tablet_id' => $tabletId,
            'product_id' => $tabletRecommendation->id,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_30_020000_backfill_device_product_channel_statuses.php');
        $migration->up();

        $this->assertTrue($aiProduct->fresh()->ai_fit_and_go_active);
        $this->assertTrue($tabletFeatured->fresh()->interactive_tablet_active);
        $this->assertTrue($tabletRecommendation->fresh()->interactive_tablet_active);
    }
}
