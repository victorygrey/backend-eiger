<?php

namespace Tests\Feature\Api;

use App\Models\Product;
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
    }

    public function test_api_can_get_standby_screen(): void
    {
        $response = $this->getJson('/api/v1/table-expedition/standby');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'title' => 'Table Expedition Hub',
                'subtitle' => 'Letakkan produk EIGER di atas sensor',
            ],
        ]);
        $this->assertCount(2, $response->json('data.instructions'));
        $response->assertJsonMissingPath('data.usage_instructions');
    }

    public function test_api_can_scan_mapped_product(): void
    {
        $similarProduct = Product::factory()->create([
            'name' => 'EIGER Equator Tarp',
            'price' => 450000,
            'is_discontinued' => false,
        ]);

        $product = Product::factory()->create([
            'name' => 'EIGER Rhinos 45L Backpack',
            'sku' => 'SKU-RHINOS-45',
            'image' => '/api/pim-media/rhinos-cover.jpg',
            'price' => 1250000,
            'description' => 'Tas carrier handal dengan sistem sirkulasi udara optimal.',
            'pim_payload' => [
                'category' => 'Mountaineering',
                'material' => 'Polyester 600D, Ripstop Nylon',
                'technology' => [['name' => 'Airflow', 'description' => 'Ventilasi optimal']],
                'performance' => [['name' => 'Load Stability', 'selected' => 3, 'rating' => 4]],
                'specification' => [['name' => 'Capacity', 'value' => '45L']],
                'customAtributes' => [['attributeCode' => 'waterproof', 'value' => 'Ya']],
            ],
            'pim_media' => [['type' => 'video', 'url' => 'https://example.com/master-rhinos.mp4']],
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-RHINOS-45-OLV-M',
            'name' => 'Rhinos 45L Olive M',
            'size' => 'M',
            'color' => 'Olive',
            'stock' => 10,
        ]);
        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-RHINOS-45-BLK-L',
            'name' => 'Rhinos 45L Black L',
            'size' => 'L',
            'color' => 'Black',
            'stock' => 5,
        ]);

        $item = TableExpeditionItem::create([
            'rfid_tag' => 'E28011606000020468900001',
            'product_id' => $product->id,
            'activity_slug' => 'mountaineering',
            'ideal_for' => 'Ekspedisi 3-5 Hari',
            'video_url' => 'https://example.com/rhinos.mp4',
            'features' => ['Ergonomic Backsystem', 'Raincover Included', 'Trekking Pole Holder'],
            'technical_details' => ['Capacity' => '45L', 'Weight' => '1.6 kg', 'Dimensions' => '65 x 32 x 26 cm'],
            'ai_summary' => 'Carrier tangguh dengan kenyamanan maksimal untuk jalur pendakian berat.',
            'similar_product_ids' => [$similarProduct->id],
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/table-expedition/scan', [
            'rfid' => 'E28011606000020468900001',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'rfid_tag' => 'E28011606000020468900001',
                'is_mapped_table' => true,
                'activity_slug' => 'mountaineering',
                'ideal_for' => 'Ekspedisi 3-5 Hari',
                'video_url' => 'https://example.com/master-rhinos.mp4',
                'ai_summary' => 'Carrier tangguh dengan kenyamanan maksimal untuk jalur pendakian berat.',
                'product' => [
                    'id' => $product->id,
                    'name' => 'EIGER Rhinos 45L Backpack',
                    'sku' => 'SKU-RHINOS-45',
                ],
            ],
        ]);

        $response->assertJsonMissingPath('data.features');
        $response->assertJsonMissingPath('data.technical_details');
        $response->assertJsonMissingPath('data.variants');
        $this->assertContains('M', $response->json('data.product.available_sizes'));
        $this->assertContains('Olive', $response->json('data.product.available_colors'));
        $this->assertContains('SKU-RHINOS-45-OLV-M', collect($response->json('data.product.variants'))->pluck('sku')->all());
        $this->assertSame(url('/api/pim-media/rhinos-cover.jpg'), $response->json('data.product.variants.0.image'));
        $this->assertSame('Airflow', $response->json('data.product.technologies.0.name'));
        $this->assertSame('Load Stability', $response->json('data.product.performances.0.name'));
        $this->assertSame('waterproof', $response->json('data.product.custom_attributes.0.attributeCode'));
        $this->assertCount(1, $response->json('data.similar_products'));
        $this->assertEquals('EIGER Equator Tarp', $response->json('data.similar_products.0.name'));

        $item->refresh();
        $this->assertNotNull($item->last_scanned_at);
    }

    public function test_api_scan_unmapped_product_returns_fallback_if_in_rfid_tags(): void
    {
        $product = Product::factory()->create([
            'name' => 'EIGER Caldera Sandal',
            'sku' => 'SKU-CALDERA',
            'price' => 250000,
        ]);

        RfidTag::create([
            'uid' => 'E28011606000020468900999',
            'product_id' => $product->id,
        ]);

        $response = $this->postJson('/api/v1/table-expedition/scan', [
            'rfid' => 'E28011606000020468900999',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'rfid_tag' => 'E28011606000020468900999',
                'is_mapped_table' => false,
                'product' => [
                    'id' => $product->id,
                    'name' => 'EIGER Caldera Sandal',
                ],
            ],
        ]);
    }

    public function test_api_scan_unknown_rfid_returns_404(): void
    {
        $response = $this->postJson('/api/v1/table-expedition/scan', [
            'rfid' => 'E28011606000020468900000_NOT_FOUND',
        ]);

        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
        ]);
    }

    public function test_api_can_trigger_item_lost(): void
    {
        $response = $this->postJson('/api/v1/table-expedition/item-lost', [
            'rfid' => 'E28011606000020468900001',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'action' => 'reset_to_standby',
                'previous_rfid' => 'E28011606000020468900001',
            ],
        ]);
    }

    public function test_api_can_compare_two_products(): void
    {
        $p1 = Product::factory()->create(['name' => 'Backpack Alpha', 'price' => 1000000]);
        $p2 = Product::factory()->create(['name' => 'Backpack Beta', 'price' => 1200000]);

        TableExpeditionItem::create([
            'rfid_tag' => 'RFID-ALPHA',
            'product_id' => $p1->id,
            'features' => ['Waterproof'],
            'technical_details' => ['Capacity' => '40L'],
            'is_active' => true,
        ]);

        TableExpeditionItem::create([
            'rfid_tag' => 'RFID-BETA',
            'product_id' => $p2->id,
            'features' => ['Water Resistant', 'Extra Pocket'],
            'technical_details' => ['Capacity' => '50L'],
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/table-expedition/compare', [
            'rfid_primary' => 'RFID-ALPHA',
            'rfid_secondary' => 'RFID-BETA',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'primary' => [
                    'rfid_tag' => 'RFID-ALPHA',
                    'product' => ['name' => 'Backpack Alpha'],
                ],
                'secondary' => [
                    'rfid_tag' => 'RFID-BETA',
                    'product' => ['name' => 'Backpack Beta'],
                ],
            ],
        ]);
        $response->assertJsonMissingPath('data.product_1');
        $response->assertJsonMissingPath('data.product_2');
    }

    public function test_api_can_get_status(): void
    {
        $product = Product::factory()->create();
        TableExpeditionItem::create([
            'rfid_tag' => 'RFID-TEST-01',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/table-expedition/status');

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'mode' => 'standby',
                'active_items' => 1,
            ],
        ]);
        $response->assertJsonMissingPath('data.active_items_count');
    }
}
