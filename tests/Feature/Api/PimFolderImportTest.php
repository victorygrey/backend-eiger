<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\PimFolderImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PimFolderImportTest extends TestCase
{
    use RefreshDatabase;

    private string $root;
    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = storage_path('framework/testing/pim-'.bin2hex(random_bytes(8)));
        File::makeDirectory($this->root.'/drop', 0755, true);
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        config(['pim.folder' => $this->root.'/drop', 'pim.media_path' => $this->root.'/media', 'pim.legacy_http_enabled' => false]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        if (str_starts_with($this->root, storage_path('framework/testing/pim-'))) File::deleteDirectory($this->root);
        parent::tearDown();
    }

    private function drop(string $batch = 'batch-1', string $date = '2026-09-08T12:00:00.000Z', string $name = 'PIM Jacket'): array
    {
        $dir = $this->root.'/drop/'.$batch.'.ready';
        File::makeDirectory($dir.'/media', 0755, true);
        File::put($dir.'/media/main.png', $this->png);
        $manifest = [
            'schema_version' => 1, 'batch_id' => $batch, 'created_at' => $date, 'article_generic' => '910012408',
            'products' => [[
                'sku' => '910012408001', 'name' => $name, 'description' => 'Windproof jacket',
                'media' => [['file' => 'media/main.png', 'sha256' => hash('sha256', $this->png), 'role' => 'main_image']],
            ]],
        ];
        File::put($dir.'/manifest.json', json_encode($manifest));
        return [$dir, $manifest];
    }

    public function test_import_is_idempotent_preserves_care_data_and_serves_media_without_http(): void
    {
        Product::factory()->create(['sku' => '910012408001', 'price' => 150000, 'stock' => 8, 'material' => 'Nylon']);
        $this->drop();
        $importer = app(PimFolderImporter::class);
        $this->assertSame(1, $importer->scan()['imported']);
        $this->assertSame(1, $importer->scan()['skipped']);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('sync_logs', 1);
        $this->assertDatabaseHas('products', ['sku' => '910012408001', 'name' => 'PIM Jacket', 'price' => 150000, 'stock' => 8, 'material' => 'Nylon']);
        $product = Product::first();
        $this->assertCount(1, $product->pim_media);
        $this->get($product->image)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('data.pim_media.0.role', 'main_image');
        Http::assertNothingSent();
    }

    public function test_partial_drops_are_ignored_and_checksum_errors_are_retryable(): void
    {
        [$dir] = $this->drop();
        rename($dir, $dir.'.partial');
        $importer = app(PimFolderImporter::class);
        $this->assertSame(['imported' => 0, 'failed' => 0, 'skipped' => 0], $importer->scan());
        rename($dir.'.partial', $dir);
        File::put($dir.'/media/main.png', $this->png.'corrupt');
        $this->assertSame(1, $importer->scan()['failed']);
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(1, $importer->scan()['skipped']);
        File::put($dir.'/media/main.png', $this->png);
        $this->assertSame(1, $importer->scan(true)['imported']);
        $this->assertDatabaseHas('pim_imports', ['batch_id' => 'batch-1', 'status' => 'imported', 'attempts' => 2]);
    }

    public function test_late_old_batch_cannot_overwrite_newer_content(): void
    {
        $this->drop('new', '2026-09-08T15:00:00.000Z', 'Newest');
        app(PimFolderImporter::class)->scan();
        $this->drop('old', '2026-09-08T11:00:00.000Z', 'Stale');
        app(PimFolderImporter::class)->scan();
        $this->assertDatabaseHas('products', ['name' => 'Newest']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_path_traversal_is_rejected_without_product_writes(): void
    {
        [$dir, $manifest] = $this->drop();
        $manifest['products'][0]['media'][0]['file'] = '../outside.png';
        File::put($dir.'/manifest.json', json_encode($manifest));
        $this->assertSame(1, app(PimFolderImporter::class)->scan()['failed']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_invalid_second_product_rolls_back_whole_batch_and_other_batches_continue(): void
    {
        [$dir, $manifest] = $this->drop('broken');
        $manifest['products'][] = array_merge($manifest['products'][0], ['sku' => 'bad', 'media' => [['file' => 'media/missing.png', 'sha256' => hash('sha256', $this->png), 'role' => 'main_image']]]);
        File::put($dir.'/manifest.json', json_encode($manifest));
        $this->assertSame(1, app(PimFolderImporter::class)->scan()['failed']);
        $this->assertDatabaseCount('products', 0);
        $this->drop('good');
        $this->assertSame(1, app(PimFolderImporter::class)->scan()['imported']);
        $this->assertDatabaseCount('products', 1);
    }

    public function test_missing_mount_fails_clearly_and_legacy_api_is_disabled(): void
    {
        config(['pim.folder' => $this->root.'/missing']);
        $this->artisan('pim:scan')->assertExitCode(1);
        $this->get('/admin/pim')->assertOk()->assertSee('Folder sumber belum dapat dibaca');
        $this->get('/admin/pim/channel-list')->assertNotFound();
        $this->postJson('/api/integrations/pim/product', [])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_admin_scan_imports_and_reports_results(): void
    {
        $this->drop();
        $this->post('/admin/pim/scan')->assertRedirect('/admin/pim');
        $this->get('/admin/pim')->assertOk()->assertSee('batch-1')->assertSee('Berhasil');
    }
}
