<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductVariant;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TableExpeditionWebTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaRoot = storage_path('framework/testing/table-expedition-'.uniqid());
        config([
            'table_expedition.media_path' => $this->mediaRoot,
            'pim.media_path' => $this->mediaRoot,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        parent::tearDown();
    }

    public function test_page_is_read_only_monitoring_of_active_master_rfid_products(): void
    {
        $readyProduct = Product::factory()->create([
            'name' => 'EIGER Equator Tarp Tent',
            'sku' => '910001234',
            'image' => '/api/pim-media/products/'.str_repeat('a', 64).'.jpg',
            'description' => 'Tenda ekspedisi empat musim.',
            'is_discontinued' => false,
        ]);
        ProductVariant::create([
            'product_id' => $readyProduct->id,
            'sku' => '910001234001',
            'name' => 'Equator Tarp Tent Standard',
            'stock' => 8,
        ]);
        ProductSpecification::create([
            'product_id' => $readyProduct->id,
            'code' => 'capacity',
            'name' => 'Kapasitas',
            'value' => '2 orang',
            'sort_order' => 0,
        ]);
        RfidTag::create([
            'uid' => 'E28011606000020468900001',
            'name' => 'Tenda Demo',
            'product_id' => $readyProduct->id,
        ]);
        TableExpeditionItem::create([
            'rfid_tag' => 'E28011606000020468900001',
            'product_id' => $readyProduct->id,
            'is_active' => true,
        ]);

        $inactiveProduct = Product::factory()->create(['name' => 'Produk Nonaktif']);
        TableExpeditionItem::create([
            'rfid_tag' => 'E28011606000020468900002',
            'product_id' => $inactiveProduct->id,
            'is_active' => false,
        ]);

        $this->get(route('admin.table-expedition.index'))
            ->assertOk()
            ->assertSee('Sumber data tunggal')
            ->assertSee('Atur RFID Table Expedition')
            ->assertSee('EIGER Equator Tarp Tent')
            ->assertSee('910001234')
            ->assertSee('Siap')
            ->assertDontSee('Produk Nonaktif')
            ->assertDontSee('Tambah Mapping RFID')
            ->assertDontSee('Edit Mapping');
    }

    public function test_incomplete_product_shows_missing_data_status(): void
    {
        $product = Product::factory()->create([
            'name' => 'Produk Belum Lengkap',
            'image' => null,
            'description' => null,
        ]);
        RfidTag::create(['uid' => 'RFID-INCOMPLETE', 'product_id' => $product->id]);
        TableExpeditionItem::create([
            'rfid_tag' => 'RFID-INCOMPLETE',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->get(route('admin.table-expedition.index'))
            ->assertOk()
            ->assertSee('Produk Belum Lengkap')
            ->assertSee('belum lengkap');
    }

    public function test_mapping_management_routes_are_removed(): void
    {
        $product = Product::factory()->create();
        $item = TableExpeditionItem::create([
            'rfid_tag' => 'RFID-LEGACY',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $this->get('/admin/table-expedition/create')->assertNotFound();
        $this->post('/admin/table-expedition/items')->assertNotFound();
        $this->get("/admin/table-expedition/items/{$item->id}/edit")->assertNotFound();
        $this->put("/admin/table-expedition/items/{$item->id}")->assertNotFound();
        $this->delete("/admin/table-expedition/items/{$item->id}")->assertNotFound();
    }

    public function test_standby_config_accepts_local_media_and_is_served_by_cms(): void
    {
        $response = $this->post(route('admin.table-expedition.config'), [
            'standby_title' => 'Explore The Wild',
            'standby_subtitle' => 'Letakkan produk di atas meja',
            'usage_instructions' => "Letakkan produk\nLihat detail produk",
            'standby_media_file' => UploadedFile::fake()->create('standby.mp4', 128, 'video/mp4'),
        ]);

        $response->assertRedirect(route('admin.table-expedition.index'));
        $this->assertSame('Explore The Wild', TableExpeditionConfig::get('standby_title'));
        $this->assertSame(['Letakkan produk', 'Lihat detail produk'], TableExpeditionConfig::get('usage_instructions'));
        $this->assertSame('video', TableExpeditionConfig::get('standby_media_type'));

        $url = TableExpeditionConfig::get('standby_media_url');
        $this->assertStringStartsWith('/api/pim-media/table-expedition/standby/video/', $url);
        $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
