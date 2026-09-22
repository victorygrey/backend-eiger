<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizeEigerMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private string $legacy;

    protected function setUp(): void
    {
        parent::setUp();
        $base = storage_path('framework/testing/media-organize-'.bin2hex(random_bytes(6)));
        $this->root = $base.'/eiger-media';
        $this->legacy = $base.'/pim-media';
        File::ensureDirectoryExists($this->root);
        File::ensureDirectoryExists($this->legacy);
        config(['pim.media_path' => $this->root, 'pim.legacy_media_path' => $this->legacy]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->root));
        parent::tearDown();
    }

    public function test_it_moves_legacy_media_and_updates_all_database_references(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $filename = hash('sha256', $png).'.png';
        File::put($this->legacy.'/'.$filename, $png);
        $oldUrl = '/api/pim-media/'.$filename;

        $product = Product::factory()->create(['sku' => 'P1', 'name' => 'Trail Bag 20L', 'image' => $oldUrl]);
        $variant = $product->variants()->create([
            'sku' => 'P1-BLK', 'name' => 'Trail Bag Black', 'price' => 100, 'stock' => 1, 'image' => $oldUrl,
        ]);
        $media = $product->mediaRelation()->create([
            'sku' => 'P1', 'role' => 'main_image', 'url' => $oldUrl, 'sort_order' => 0,
        ]);
        $technology = $product->technologiesRelation()->create([
            'name' => 'Dry Tech', 'image_url' => $oldUrl, 'sort_order' => 0,
        ]);

        $newUrl = '/api/pim-media/products/photos/trail-bag-20l--p1/'.$filename;
        $this->artisan('media:organize --remove-legacy')->assertSuccessful();

        $this->assertSame($newUrl, $product->fresh()->image);
        $this->assertSame($newUrl, $variant->fresh()->image);
        $this->assertSame($newUrl, $media->fresh()->url);
        $this->assertSame($newUrl, $technology->fresh()->image_url);
        $this->assertFileExists($this->root.'/products/photos/trail-bag-20l--p1/'.$filename);
        $this->assertFileDoesNotExist($this->legacy.'/'.$filename);
        $this->get($newUrl)->assertOk()->assertHeader('Content-Type', 'image/png');
    }
}
