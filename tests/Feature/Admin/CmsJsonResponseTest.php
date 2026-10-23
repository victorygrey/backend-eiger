<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\FitAndGoDevice;
use App\Models\Tablet;
use App\Models\RfidTag;
use App\Models\TableExpeditionItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CmsJsonResponseTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\ConfiguresFitAndGoProducts;

    protected bool $autoAuthenticate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('X-CMS-Interface', 'nuxt');
    }

    public function test_session_bootstraps_csrf_without_exposing_user_credentials(): void
    {
        $this->getJson('/cms-api/session')->assertOk()->assertJsonPath('user', null)
            ->assertJsonStructure(['csrf_token', 'api_version']);
        $admin = User::factory()->superadmin()->create();
        $response = $this->actingAs($admin)->getJson('/cms-api/session')->assertOk()
            ->assertJsonPath('user.superadmin', true)->assertJsonPath('user.email', $admin->email);
        $this->assertStringNotContainsString('password', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_catalog_json_keeps_nested_variants_channel_flags_and_pagination(): void
    {
        $product = Product::factory()->create(['sku' => '910999901', 'ai_fit_and_go_active' => true, 'interactive_tablet_active' => false]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => '910999901001', 'color' => 'BLACK']);
        $this->actingAs(User::factory()->superadmin()->create())->getJson('/admin/products')
            ->assertOk()->assertJsonPath('meta.api_version', 1)->assertJsonPath('data.products.total', 1)
            ->assertJsonPath('data.products.data.0.ai_fit_and_go_active', true)
            ->assertJsonPath('data.products.data.0.interactive_tablet_active', false)
            ->assertJsonPath('data.products.data.0.variants.0.color', 'BLACK')
            ->assertJsonMissingPath('data.products.data.0.pim_payload');
        $this->get('/admin/products')->assertOk()->assertViewIs('admin.products.index');
    }

    public function test_json_transport_cannot_bypass_auth_permissions_or_channel_lock(): void
    {
        $product = Product::factory()->create();
        $this->getJson('/admin/products')->assertUnauthorized();
        Role::create(['slug' => 'json-reader', 'name' => 'JSON Reader', 'is_active' => true, 'permissions' => ['products.view']]);
        $this->actingAs(User::factory()->create(['role' => 'json-reader']))
            ->getJson('/admin/products')->assertOk();
        $this->patchJson('/admin/products/channel-lock', ['unlocked' => true])->assertForbidden();
        $this->deleteJson('/admin/products/'.$product->id)->assertForbidden();
        $this->getJson('/admin/users')->assertForbidden();
        $this->actingAs(User::factory()->superadmin()->create())
            ->patchJson('/admin/products/'.$product->id.'/channel', ['channel' => 'ai_fit_and_go_active', 'active' => true])->assertStatus(423);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_fit_product_summary_preserves_catalog_order_floor_selection_and_configuration(): void
    {
        $older = Product::factory()->create([
            'sku' => '910999910', 'category' => 'Jacket', 'ai_fit_and_go_active' => true,
            'pim_catalog_active' => true, 'is_discontinued' => false, 'created_at' => now()->subDay(),
        ]);
        $newer = Product::factory()->create([
            'sku' => '910999911', 'category' => 'Jacket', 'ai_fit_and_go_active' => true,
            'pim_catalog_active' => true, 'is_discontinued' => false, 'created_at' => now(),
        ]);
        $this->configureFitProduct($older);
        $floorOne = FitAndGoDevice::where('device_code', FitAndGoDevice::STATIC_SLOTS[1]['device_code'])->firstOrFail();
        $floorTwo = FitAndGoDevice::where('device_code', FitAndGoDevice::STATIC_SLOTS[2]['device_code'])->firstOrFail();
        \App\Models\FitAndGoItemVisibility::create([
            'device_id' => $floorOne->id, 'product_id' => $older->id,
            'category_code' => 'apparel', 'is_visible' => true,
        ]);
        $this->actingAs(User::factory()->superadmin()->create());
        $response = $this->getJson('/admin/fit-and-go?product_summary=1&device_id='.$floorOne->id)
            ->assertOk()
            ->assertJsonPath('data.aiProducts.0.id', $newer->id)
            ->assertJsonPath('data.aiProducts.1.id', $older->id)
            ->assertJsonPath('data.aiProducts.1.presentation_configured', true)
            ->assertJsonPath('data.productGroups.apparel.products.0.id', $newer->id)
            ->assertJsonPath('data.selectedProductIds', [$older->id])
            ->assertJsonPath('data.selectedDevice.id', $floorOne->id)
            ->assertJsonMissingPath('data.aiProducts.0.variants')
            ->assertJsonMissingPath('data.productGroups.apparel.products.0.pim_media');
        $this->assertEqualsCanonicalizing(
            ['id', 'sku', 'name', 'image', 'stock', 'variants_count', 'presentation_configured'],
            array_keys($response->json('data.aiProducts.0'))
        );
        $this->getJson('/admin/fit-and-go?product_summary=1&device_id='.$floorTwo->id)
            ->assertOk()->assertJsonPath('data.selectedProductIds', []);
        $this->getJson('/admin/fit-and-go')->assertOk()->assertJsonStructure([
            'data' => ['aiProducts' => [['variants', 'custom_attributes', 'pim_media']]],
        ]);
        $this->getJson('/admin/fit-and-go/products/'.$older->id.'/presentation')
            ->assertOk()->assertJsonStructure(['colors', 'photos']);
    }

    public function test_success_redirects_and_validation_failures_have_json_responses(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        $this->patchJson('/admin/products/channel-lock', ['unlocked' => true])->assertOk()->assertJsonPath('success', true);
        $this->patchJson('/admin/products/channel-lock', ['unlocked' => 'bad'])->assertUnprocessable()->assertJsonValidationErrors('unlocked');
        $this->getJson('/admin/products')->assertOk()->assertJsonPath('data.channelSettingsUnlocked', true);
    }

    public function test_login_reports_invalid_credentials_and_then_allows_a_valid_login(): void
    {
        $user = User::factory()->superadmin()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'invalid-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('success', true);
        $this->getJson('/cms-api/session')->assertJsonPath('user.id', $user->id);
    }

    public function test_inactive_account_cannot_read_the_session_api(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->getJson('/cms-api/session')->assertForbidden();
    }

    public function test_all_admin_modules_return_json_without_exposing_device_secrets(): void
    {
        $this->actingAs(User::factory()->superadmin()->create());
        foreach (['dashboard', 'products', 'zones', 'rfid-tags', 'fit-and-go', 'tablets', 'led-ambience', 'table-expedition', 'print-history', 'users', 'roles', 'integrations', 'sync-logs', 'profile'] as $module) {
            $response = $this->getJson('/admin/'.$module)->assertOk()->assertJsonPath('meta.api_version', 1);
            foreach (['activation_code_hash', 'activation_code_encrypted', 'device_token_hash', 'password_hash'] as $secret) {
                $this->assertStringNotContainsString('"'.$secret.'"', $response->getContent(), $module);
            }
        }
        $device = FitAndGoDevice::first();
        $tablet = Tablet::first();
        if ($device) {
            $this->getJson('/admin/fit-and-go/devices/'.$device->id.'/edit')->assertOk()->assertJsonStructure(['data' => ['device' => ['activation_code']]]);
        }
        if ($tablet) {
            $this->getJson('/admin/tablets/'.$tablet->id.'/edit')->assertOk()->assertJsonStructure(['data' => ['tablet' => ['activation_code']]]);
        }
    }

    public function test_table_rfid_uid_is_not_replaced_by_the_loaded_rfid_relation(): void
    {
        $product = Product::factory()->create();
        $tag = RfidTag::create(['uid' => 'E28000123456', 'product_id' => $product->id]);
        TableExpeditionItem::create(['rfid_tag' => $tag->uid, 'product_id' => $product->id, 'is_active' => true]);
        $this->actingAs(User::factory()->superadmin()->create())->getJson('/admin/table-expedition')
            ->assertOk()->assertJsonPath('data.items.0.rfid_tag', $tag->uid)
            ->assertJsonPath('data.items.0.rfidTag.uid', $tag->uid)
            ->assertJsonPath('data.items.0.product.id', $product->id);
    }
}
