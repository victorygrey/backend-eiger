<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableExpeditionWebTest extends TestCase
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

    public function test_table_expedition_page_loads(): void
    {
        $product = Product::factory()->create([
            'name' => 'EIGER Equator Tarp Tent',
            'is_discontinued' => false,
        ]);

        TableExpeditionItem::create([
            'rfid_tag'      => 'E28011606000020468900001',
            'product_id'    => $product->id,
            'activity_slug' => 'mountaineering',
            'ideal_for'     => 'Ekspedisi Cuaca Ekstrem',
            'is_active'     => true,
        ]);

        $response = $this->get(route('admin.table-expedition.index'));

        $response->assertStatus(200);
        $response->assertSee('Table Expedition');
        $response->assertSee('E28011606000020468900001');
        $response->assertSee('EIGER Equator Tarp Tent');
        $response->assertSee('Ekspedisi Cuaca Ekstrem');
    }

    public function test_can_create_rfid_mapping(): void
    {
        $product = Product::factory()->create(['is_discontinued' => false]);
        $similarProduct = Product::factory()->create(['is_discontinued' => false]);

        $postData = [
            'rfid_tag'             => 'E28011606000020468900002',
            'product_id'           => $product->id,
            'activity_slug'        => 'mountaineering',
            'ideal_for'            => 'Pendakian 3000 mdpl',
            'video_url'            => 'https://example.com/demo.mp4',
            'features'             => "Waterproof 10000mm\nWindproof Membrane\nSealed Seams",
            'technical_details'    => "Weight: 450g\nCapacity: 35L\nMaterial: Ripstop Cordura",
            'ai_summary'           => 'Jaket teknis terbaik untuk medan basah dan berangin.',
            'similar_product_ids'  => [$similarProduct->id],
            'notes'                => 'Unit display meja 1',
            'is_active'            => '1',
        ];

        $response = $this->post(route('admin.table-expedition.store'), $postData);

        $response->assertRedirect(route('admin.table-expedition.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('table_expedition_items', [
            'rfid_tag'      => 'E28011606000020468900002',
            'product_id'    => $product->id,
            'activity_slug' => 'mountaineering',
            'ideal_for'     => 'Pendakian 3000 mdpl',
            'notes'         => 'Unit display meja 1',
            'is_active'     => true,
        ]);

        $item = TableExpeditionItem::where('rfid_tag', 'E28011606000020468900002')->first();
        $this->assertNotNull($item);
        $this->assertEmpty($item->features);
        $this->assertEmpty($item->technical_details);
        $this->assertEquals([$similarProduct->id], $item->similar_product_ids);
    }

    public function test_mapping_form_shows_visual_catalog_and_read_only_master_details(): void
    {
        Product::factory()->create(['is_discontinued' => false, 'name' => 'EIGER Visual Product']);
        $this->get(route('admin.table-expedition.create'))
            ->assertOk()
            ->assertSee('mapping-product-option')
            ->assertSee('EIGER Visual Product')
            ->assertDontSee('name="features"', false)
            ->assertDontSee('name="technical_details"', false);
    }

    public function test_cannot_create_duplicate_rfid(): void
    {
        $product = Product::factory()->create();

        TableExpeditionItem::create([
            'rfid_tag'   => 'E28011606000020468900001',
            'product_id' => $product->id,
        ]);

        $response = $this->post(route('admin.table-expedition.store'), [
            'rfid_tag'   => 'E28011606000020468900001',
            'product_id' => $product->id,
        ]);

        $response->assertSessionHasErrors('rfid_tag');
        $this->assertEquals(1, TableExpeditionItem::where('rfid_tag', 'E28011606000020468900001')->count());
    }

    public function test_can_update_rfid_mapping(): void
    {
        $product = Product::factory()->create();
        $item = TableExpeditionItem::create([
            'rfid_tag'      => 'E28011606000020468900001',
            'product_id'    => $product->id,
            'ideal_for'     => 'Old Ideal',
            'features'      => ['Feature 1'],
            'is_active'     => true,
        ]);

        $response = $this->put(route('admin.table-expedition.update', $item), [
            'rfid_tag'      => 'E28011606000020468900001',
            'product_id'    => $product->id,
            'ideal_for'     => 'Updated Ideal For Expeditions',
            'features'      => "Updated Feature A\nUpdated Feature B",
            'is_active'     => '1',
        ]);

        $response->assertRedirect(route('admin.table-expedition.index'));
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertEquals('Updated Ideal For Expeditions', $item->ideal_for);
        $this->assertEquals(['Feature 1'], $item->features);
    }

    public function test_can_delete_rfid_mapping(): void
    {
        $product = Product::factory()->create();
        $item = TableExpeditionItem::create([
            'rfid_tag'   => 'E28011606000020468900001',
            'product_id' => $product->id,
        ]);

        $response = $this->delete(route('admin.table-expedition.destroy', $item));

        $response->assertRedirect(route('admin.table-expedition.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('table_expedition_items', [
            'id' => $item->id,
        ]);
    }

    public function test_can_update_standby_config(): void
    {
        $response = $this->post(route('admin.table-expedition.config'), [
            'standby_title'      => 'Explore The Wild - EIGER Table',
            'standby_subtitle'   => 'Sentuh sensor RFID dengan produk pilihan',
            'usage_instructions' => "Langkah 1: Dekatkan produk ke sensor\nLangkah 2: Amati detail material",
        ]);

        $response->assertRedirect(route('admin.table-expedition.index'));
        $response->assertSessionHas('success');

        $this->assertEquals('Explore The Wild - EIGER Table', TableExpeditionConfig::get('standby_title'));
        $this->assertEquals('Sentuh sensor RFID dengan produk pilihan', TableExpeditionConfig::get('standby_subtitle'));
        $instructions = TableExpeditionConfig::get('usage_instructions');
        $this->assertCount(2, $instructions);
        $this->assertEquals('Langkah 1: Dekatkan produk ke sensor', $instructions[0]);
    }
}
