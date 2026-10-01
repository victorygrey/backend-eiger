<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Services\DeviceImageWebpService;
use App\Support\DeviceProductPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DeviceImageWebpTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaDirectory;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mediaDirectory = sys_get_temp_dir().'/device-webp-'.bin2hex(random_bytes(6));
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        config([
            'pim.media_path' => $this->mediaDirectory,
            'pim.legacy_media_path' => $this->mediaDirectory.'/legacy',
            'pim.device_image_webp_quality' => 80,
            'pim.device_image_max_edge' => 1600,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaDirectory);
        parent::tearDown();
    }

    public function test_device_payload_uses_cached_webp_copies_for_local_product_photos(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('The current PHP runtime does not have WebP-enabled GD. The CMS Docker image installs it.');
        }

        $original = $this->storeSourceImage();
        $product = Product::factory()->create([
            'image' => '/api/pim-media/'.$original,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => '910000001001',
            'name' => 'WebP Variant',
            'price' => 100000,
            'stock' => 1,
            'image' => '/api/pim-media/'.$original,
        ]);
        ProductMedia::create([
            'product_id' => $product->id,
            'external_id' => 'gallery-1',
            'role' => 'gallery',
            'media_type' => 'image',
            'url' => '/api/pim-media/'.$original,
            'mime_type' => 'image/png',
        ]);
        ProductMedia::create([
            'product_id' => $product->id,
            'external_id' => 'video-1',
            'role' => 'product_video',
            'media_type' => 'video',
            'url' => '/api/pim-media/products/videos/webp-test--910000001/'.str_repeat('f', 64).'.mp4',
            'mime_type' => 'video/mp4',
        ]);

        $payload = DeviceProductPayload::make($product->fresh());

        $webpUrl = $payload['image'];
        $this->assertStringContainsString('/device-webp/', $webpUrl);
        $this->assertStringEndsWith('.webp', $webpUrl);
        $this->assertSame($webpUrl, $payload['variants'][0]['image']);
        $this->assertSame($webpUrl, collect($payload['media'])->firstWhere('role', 'gallery')['url']);
        $this->assertStringEndsWith('.mp4', collect($payload['media'])->firstWhere('role', 'product_video')['url']);

        $path = (string) parse_url($webpUrl, PHP_URL_PATH);
        $relative = substr($path, strlen('/api/pim-media/'));
        $webpPath = $this->mediaDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $this->assertFileExists($webpPath);
        $this->assertSame('image/webp', getimagesize($webpPath)['mime']);
        $this->get($path)->assertOk()->assertHeader('content-type', 'image/webp');

        $this->assertSame($webpUrl, app(DeviceImageWebpService::class)->url('/api/pim-media/'.$original));
        $this->assertSame('https://example.com/remote.jpg', app(DeviceImageWebpService::class)->url('https://example.com/remote.jpg'));
        $this->assertSame($variant->image, '/api/pim-media/'.$original);
    }

    private function storeSourceImage(): string
    {
        $hash = hash('sha256', $this->png);
        $relative = 'products/photos/webp-test--910000001/'.$hash.'.png';
        $path = $this->mediaDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->png);

        return $relative;
    }
}
