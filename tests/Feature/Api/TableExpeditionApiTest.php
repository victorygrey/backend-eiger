<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductActivity;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableExpeditionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TableExpeditionConfig::set('standby_title', 'Table Expedition Hub');
        TableExpeditionConfig::set('standby_subtitle', 'Letakkan produk EIGER di atas sensor');
        TableExpeditionConfig::set('usage_instructions', [
            'Letakkan produk di area scanner',
            'Lihat informasi produk',
        ]);
        TableExpeditionConfig::set('standby_media_type', 'video');
        TableExpeditionConfig::set('standby_media_url', '/api/pim-media/table-expedition/standby/video/'.str_repeat('a', 64).'.mp4');
    }

    public function test_standby_returns_global_content_and_local_media_contract(): void
    {
        $this->getJson('/api/v1/table-expedition/standby')
            ->assertOk()
            ->assertJsonPath('screen', 'standby')
            ->assertJsonPath('data.title', 'Table Expedition Hub')
            ->assertJsonPath('data.instructions.0', 'Letakkan produk di area scanner')
            ->assertJsonPath('data.media.type', 'video')
            ->assertJsonPath('data.media.url', url(TableExpeditionConfig::get('standby_media_url')));
    }

    public function test_scan_returns_full_product_and_admin_selected_comparisons_in_saved_order(): void
    {
        $product = $this->activeProduct('RFID-PRIMARY', [
            'name' => 'EIGER Rhinos 45L Backpack',
            'sku' => '910001111',
            'category' => 'Backpack',
            'stock' => 10,
            'image' => '/api/pim-media/products/'.str_repeat('b', 64).'.jpg',
            'description' => 'Carrier ekspedisi.',
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => '910001111001',
            'name' => 'Rhinos 45L Olive M',
            'size' => 'M',
            'color' => 'Olive',
            'stock' => 10,
        ]);
        ProductSpecification::create([
            'product_id' => $product->id,
            'code' => 'capacity',
            'name' => 'Capacity',
            'value' => '45L',
            'sort_order' => 0,
        ]);
        ProductActivity::create([
            'product_id' => $product->id,
            'name' => 'Hiking',
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        $comparison = $this->activeProduct('RFID-SIMILAR', [
            'name' => 'EIGER Equator Backpack',
            'sku' => '910002222',
            'category' => 'Backpack',
            'stock' => 4,
            'is_discontinued' => false,
        ]);
        ProductActivity::create([
            'product_id' => $comparison->id,
            'name' => 'Hiking',
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        $unrelated = $this->activeProduct('RFID-UNRELATED', [
            'name' => 'EIGER Daily Shirt',
            'sku' => '910003333',
            'category' => 'Shirt',
            'stock' => 5,
            'is_discontinued' => false,
        ]);
        ProductActivity::create([
            'product_id' => $unrelated->id,
            'name' => 'Daily Wear',
            'is_selected' => true,
            'sort_order' => 0,
        ]);

        TableExpeditionItem::where('product_id', $product->id)->update([
            'similar_product_ids' => [$comparison->id],
        ]);

        $response = $this->postJson('/api/v1/table-expedition/scan', ['rfid' => 'rfid-primary']);

        $response->assertOk()
            ->assertJsonPath('source', 'master_rfid')
            ->assertJsonPath('screen', 'product_detail')
            ->assertJsonPath('data.rfid_tag', 'RFIDPRIMARY')
            ->assertJsonPath('data.readiness.ready', true)
            ->assertJsonPath('data.product.id', $product->id)
            ->assertJsonPath('data.product.variants.0.sku', '910001111001')
            ->assertJsonPath('data.comparison_products.0.id', $comparison->id)
            ->assertJsonMissingPath('data.ai_summary')
            ->assertJsonMissingPath('data.ideal_for')
            ->assertJsonMissingPath('data.similar_products');

        $this->assertCount(1, $response->json('data.comparison_products'));
        $this->assertNotContains($unrelated->id, collect($response->json('data.comparison_products'))->pluck('id'));
        $this->assertNotNull(TableExpeditionItem::where('rfid_tag', 'RFIDPRIMARY')->firstOrFail()->last_scanned_at);
        $this->assertNotNull(RfidTag::where('uid', 'RFIDPRIMARY')->firstOrFail()->last_scanned_at);
    }

    public function test_scan_rejects_unknown_or_inactive_rfid(): void
    {
        $product = Product::factory()->create();
        RfidTag::create(['uid' => 'RFIDINACTIVE', 'product_id' => $product->id]);
        TableExpeditionItem::create([
            'rfid_tag' => 'RFIDINACTIVE',
            'product_id' => $product->id,
            'is_active' => false,
        ]);

        $this->postJson('/api/v1/table-expedition/scan', ['rfid' => 'RFIDINACTIVE'])
            ->assertNotFound()
            ->assertJsonPath('matched', false);
        $this->postJson('/api/v1/table-expedition/scan', ['rfid' => 'UNKNOWN'])
            ->assertNotFound()
            ->assertJsonPath('matched', false);
    }

    public function test_scan_accepts_formatted_legacy_rfid_values(): void
    {
        $product = Product::factory()->create(['name' => 'Legacy RFID Product']);
        RfidTag::create(['uid' => 'AA:BB:CC:DD', 'product_id' => $product->id]);
        TableExpeditionItem::create([
            'rfid_tag' => 'AA:BB:CC:DD',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/table-expedition/scan', ['rfid' => 'aa-bb-cc-dd'])
            ->assertOk()
            ->assertJsonPath('data.rfid_tag', 'AABBCCDD')
            ->assertJsonPath('data.product.id', $product->id);

        $this->assertNotNull(RfidTag::where('uid', 'AA:BB:CC:DD')->firstOrFail()->last_scanned_at);
    }

    public function test_compare_only_accepts_two_different_active_table_products(): void
    {
        $primary = $this->activeProduct('RFID-ALPHA', [
            'name' => 'Backpack Alpha',
            'price' => 1000000,
            'stock' => 8,
            'is_discontinued' => false,
        ]);
        $secondary = $this->activeProduct('RFID-BETA', [
            'name' => 'Backpack Beta',
            'price' => 1200000,
            'stock' => 5,
            'is_discontinued' => false,
        ]);
        $inactive = Product::factory()->create(['name' => 'Inactive Product']);
        $unselected = $this->activeProduct('RFID-GAMMA', ['name' => 'Backpack Gamma']);
        TableExpeditionItem::where('product_id', $primary->id)->update([
            'similar_product_ids' => [$secondary->id],
        ]);

        $this->postJson('/api/v1/table-expedition/compare', [
            'product_id_1' => $primary->id,
            'product_id_2' => $secondary->id,
        ])->assertOk()
            ->assertJsonPath('data.primary.product.name', 'Backpack Alpha')
            ->assertJsonPath('data.secondary.product.name', 'Backpack Beta')
            ->assertJsonPath('data.comparison.price_difference', 200000)
            ->assertJsonMissingPath('data.ai_comparison_summary');

        $this->postJson('/api/v1/table-expedition/compare', [
            'product_id_1' => $primary->id,
            'product_id_2' => $inactive->id,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/table-expedition/compare', [
            'product_id_1' => $primary->id,
            'product_id_2' => $unselected->id,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Produk kedua tidak terdaftar sebagai pilihan komparasi untuk produk utama.');

        $this->postJson('/api/v1/table-expedition/compare', [
            'product_id_1' => $primary->id,
            'product_id_2' => $primary->id,
        ])->assertUnprocessable();
    }

    public function test_item_lost_and_status_use_clean_contract(): void
    {
        $product = $this->activeProduct('RFID-STATUS', [
            'name' => 'Incomplete Product',
            'image' => null,
            'description' => null,
        ]);

        $this->postJson('/api/v1/table-expedition/item-lost', ['rfid' => 'rfid-status'])
            ->assertOk()
            ->assertJsonPath('data.previous_rfid', 'RFIDSTATUS');

        $this->getJson('/api/v1/table-expedition/status')
            ->assertOk()
            ->assertJsonPath('data.active_items', 1)
            ->assertJsonPath('data.ready_items', 0)
            ->assertJsonPath('data.incomplete_items', 1)
            ->assertJsonPath('data.standby_media_configured', true);

        $this->assertNotNull($product);
    }

    /** @param array<string, mixed> $attributes */
    private function activeProduct(string $rfid, array $attributes): Product
    {
        $product = Product::factory()->create($attributes + ['is_discontinued' => false]);
        $uid = RfidTag::canonicalUid($rfid);
        RfidTag::create(['uid' => $uid, 'product_id' => $product->id]);
        TableExpeditionItem::create([
            'rfid_tag' => $uid,
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        return $product;
    }
}
