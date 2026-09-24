<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\PimHttpMediaImporter;
use App\Services\PimPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PimPayloadContractTest extends TestCase
{
    use RefreshDatabase;

    private string $mediaDir;

    private string $png;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mediaDir = sys_get_temp_dir().'/pim-contract-'.bin2hex(random_bytes(6));
        $this->png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        config(['pim.legacy_http_enabled' => true, 'pim.inbound_token' => 'test',
            'pim.copy_http_media' => true, 'pim.media_path' => $this->mediaDir,
            'pim.url' => 'http://pim.test', 'pim.media_hosts' => ['storage.eigeradventure.com']]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaDir);
        parent::tearDown();
    }

    private function payload(): array
    {
        return ['product' => ['generic' => 'P1', 'name' => 'Bag', 'weight' => 725,
            'mainImage' => 'https://storage.eigeradventure.com/main.JPG?signature='.str_repeat('a', 700),
            'variant' => [['sku' => 'P1-M', 'name' => 'Bag M', 'color' => 'BLK', 'size' => 'M', 'moq' => '1', 'ecmsku' => 'ECM1', 'customAttributes' => []]],
            'customAtributes' => [['attributeCode' => 'long_description', 'value' => 'Real description']],
            'media' => [['attributeCode' => 'SIZE_CHART', 'files' => [['value' => 'https://storage.eigeradventure.com/chart.jpg']]]],
            'technology' => [[
                'id' => 'T1', 'name' => 'Technology from PIM',
                'image' => 'https://storage.eigeradventure.com/technology.jpg',
            ]],
            'activity' => [['name' => 'Hiking', 'rating' => '4']],
            'performance' => [[
                'id' => 'PERF-1', 'name' => 'Grip', 'description' => 'Cengkeraman optimal',
                'selected' => '3', 'rating' => '5', 'desc_rating' => 'Sangat baik',
            ]],
            'specification' => [['code' => 'PRODUCT_WEIGHT', 'value' => '725']]],
            'image' => ['generic' => [[
                'sku' => 'P1',
                'image' => [[
                    'id' => 'IMG-1', 'type' => 'main_image', 'source' => 'PIM',
                    'url' => 'https://storage.eigeradventure.com/main.JPG?signature='.str_repeat('a', 700),
                ]],
            ]], 'variant' => []]];
    }

    private function storedMediaUrl(string $name = 'Bag', string $sku = 'P1'): string
    {
        return '/api/pim-media/products/photos/'.strtolower($name).'--'.strtolower($sku).'/'.hash('sha256', $this->png).'.png';
    }

    public function test_publish_retains_full_contract_and_core_api_exposes_it(): void
    {
        Http::fake(['storage.eigeradventure.com/*' => Http::response($this->png, 200)]);
        $existing = Product::factory()->create(['sku' => 'P1-M', 'price' => 250000, 'stock' => 5]);
        $payload = $this->payload();
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertOk();
        $saved = $existing->fresh();
        $payload['product']['customAtributes'][] = ['attributeCode' => 'activity', 'value' => PimPayload::dummyActivity('P1')];
        $this->assertSame($payload['product'], $saved->pim_payload);
        $this->assertSame($payload['image'], $saved->pim_image_payload);
        $this->assertEquals(250000, $saved->price);
        $this->assertSame(5, $saved->stock);
        $this->assertSame($this->storedMediaUrl(), $saved->image);
        $parent = Product::where('sku', 'P1')->firstOrFail();
        $this->assertSame('1', $parent->variants()->first()->moq);
        $this->assertSame('ECM1', $parent->variants()->first()->ecmsku);
        $this->assertSame($this->storedMediaUrl(), $parent->variants()->first()->image);
        $this->assertSame('SIZE_CHART', $saved->pim_media[1]['role']);
        $this->assertTrue(collect($parent->pim_media)->every(fn ($item) => str_starts_with($item['url'], '/api/pim-media/')));
        $this->assertCount(3, $parent->mediaRelation()->whereNull('product_variant_id')->get());
        $this->assertCount(1, $parent->mediaRelation()->whereNotNull('product_variant_id')->get());
        $this->assertFalse($parent->mediaRelation()->whereNotNull('product_variant_id')->get()->contains('role', 'SIZE_CHART'));
        $this->getJson('/api/products/'.$saved->id)->assertOk()
            ->assertJsonPath('data.pim_payload.weight', 725)
            ->assertJsonPath('data.pim_payload.variant.0.size', 'M')
            ->assertJsonPath('data.pim_payload.technology.0.id', 'T1');
        $this->getJson('/api/products/'.$parent->id)->assertOk()
            ->assertJsonPath('data.variants.0.sku', 'P1-M')
            ->assertJsonPath('data.variants.0.image', url($this->storedMediaUrl()));

        $this->get(route('admin.products.edit', $parent))->assertOk()
            ->assertSee('1 Foto Tersedia')
            ->assertSee(url($this->storedMediaUrl()), false)
            ->assertSee('Performa Produk (Performance)')
            ->assertSee('Grip')
            ->assertSee('data-pim-extra-media="image"', false)
            ->assertSee('src="'.$this->storedMediaUrl().'"', false)
            ->assertSee('title="Buka ukuran penuh"', false);
    }

    public function test_publish_normalizes_queryable_pim_data_out_of_products_table(): void
    {
        Http::fake(['storage.eigeradventure.com/*' => Http::response($this->png, 200)]);
        $payload = $this->payload();
        $payload['product']['variant'][0]['customAttributes'] = [
            ['attributeCode' => 'lining', 'value' => 'Mesh'],
        ];

        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertOk();
        $parent = Product::where('sku', 'P1')->firstOrFail();
        $variant = $parent->variants()->where('sku', 'P1-M')->firstOrFail();

        $this->assertFalse(Schema::hasColumn('products', 'pim_payload'));
        $this->assertFalse(Schema::hasColumn('products', 'pim_media'));
        $this->assertDatabaseHas('product_pim_records', ['product_id' => $parent->id, 'generic_sku' => 'P1', 'source' => 'pim-http']);
        $this->assertDatabaseHas('product_custom_attributes', ['product_id' => $parent->id, 'attribute_code' => 'long_description', 'value' => 'Real description']);
        $this->assertDatabaseHas('product_technologies', ['product_id' => $parent->id, 'pim_id' => 'T1', 'name' => 'Technology from PIM']);
        $this->assertDatabaseHas('product_activities', ['product_id' => $parent->id, 'name' => 'Hiking', 'rating' => 4]);
        $this->assertDatabaseHas('product_performances', ['product_id' => $parent->id, 'name' => 'Grip', 'rating' => 5, 'is_selected' => true]);
        $this->assertDatabaseHas('product_specifications', ['product_id' => $parent->id, 'code' => 'PRODUCT_WEIGHT', 'value' => '725']);
        $this->assertDatabaseHas('product_media', ['product_id' => $parent->id, 'role' => 'main_image']);
        $this->assertDatabaseHas('product_variant_attributes', ['product_variant_id' => $variant->id, 'attribute_code' => 'lining', 'value' => 'Mesh']);
        $this->assertSame('Technology from PIM', $parent->fresh()->technologies[0]['name']);
        $this->assertSame('Grip', $parent->fresh()->performances[0]['name']);
    }

    public function test_blank_activity_attribute_gets_stable_dummy_and_real_value_is_preserved(): void
    {
        $service = app(PimPayload::class);
        $base = $this->payload();
        $base['product']['customAtributes'][] = ['attributeCode' => 'activity', 'value' => ''];
        $normalized = $service->validate($base);
        $activity = collect($normalized['product']['customAtributes'])->firstWhere('attributeCode', 'activity');
        $this->assertContains($activity['value'], ['Camping', 'Hiking', 'Running', 'Riding', 'Travelling']);

        $base['product']['customAtributes'][1]['value'] = 'Climbing';
        $normalized = $service->validate($base);
        $this->assertSame('Climbing', collect($normalized['product']['customAtributes'])->firstWhere('attributeCode', 'activity')['value']);
    }

    public function test_existing_pim_variant_url_uses_the_public_cms_media_copy(): void
    {
        $hash = hash('sha256', $this->png);
        File::ensureDirectoryExists($this->mediaDir);
        file_put_contents($this->mediaDir.'/'.$hash.'.png', $this->png);

        $product = Product::factory()->create();
        $product->variants()->create([
            'sku' => '910012408001',
            'name' => 'Existing variant',
            'price' => 100000,
            'stock' => 1,
            'image' => 'http://pim.test/media/'.$hash.'.png',
        ]);

        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('data.variants.0.image', url('/api/pim-media/'.$hash.'.png'));
    }

    public function test_form_accepts_signed_url_and_legacy_attribute_spelling(): void
    {
        Http::fake(['storage.eigeradventure.com/*' => Http::response($this->png, 200)]);
        $payload = $this->payload();
        $payload['product']['customAttributes'] = $payload['product']['customAtributes'];
        unset($payload['product']['customAtributes']);
        $this->post('/admin/products', ['sku' => 'P1-M', 'name' => 'Bag M', 'price' => 10,
            'image' => $payload['product']['mainImage'],
            'pim_payload_json' => json_encode($payload['product']),
            'pim_image_payload_json' => json_encode($payload['image'])])->assertRedirect('/admin/products');
        $saved = Product::first();
        $this->assertSame('Real description', $saved->pim_payload['customAtributes'][0]['value']);
        $this->assertCount(3, $saved->pim_media);
        $this->assertSame('SIZE_CHART', $saved->pim_media[1]['role']);
        $path = $this->mediaDir.'/products/photos/bag--p1/'.hash('sha256', $this->png).'.png';
        $this->assertFileExists($path);
        if (DIRECTORY_SEPARATOR === '/') {
            $this->assertSame(0644, fileperms($path) & 0777);
        }
    }

    public function test_rejects_cross_article_images_before_download_or_write(): void
    {
        $payload = $this->payload();
        $payload['image']['generic'] = [['sku' => 'OTHER', 'image' => []]];
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertUnprocessable();
        $payload['image'] = ['variant' => [['sku' => 'OTHER', 'image' => []]]];
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_failed_download_does_not_update_existing_product(): void
    {
        Http::fake(['*' => Http::response('Expired', 403)]);
        $saved = Product::factory()->create(['sku' => 'P1-M', 'name' => 'Original']);
        $this->withToken('test')->postJson('/api/integrations/pim/product', $this->payload())->assertUnprocessable();
        $this->assertSame('Original', $saved->fresh()->name);
        $this->assertNull($saved->fresh()->pim_payload);
    }

    public function test_media_download_retries_transient_connection_failures(): void
    {
        config(['pim.media_download_attempts' => 2]);
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new ConnectionException('Temporary DNS failure');
            }

            return Http::response($this->png, 200);
        });

        $media = app(PimHttpMediaImporter::class)->import(
            'https://storage.eigeradventure.com/retry.png',
            'main_image',
            ['product_name' => 'Retry Bag', 'generic_sku' => 'P1'],
        );

        $this->assertSame(2, $attempts);
        $this->assertSame(
            '/api/pim-media/products/photos/retry-bag--p1/'.hash('sha256', $this->png).'.png',
            $media['url'],
        );
    }

    public function test_publish_keeps_remote_media_url_when_all_download_attempts_fail(): void
    {
        config(['pim.media_download_attempts' => 1]);
        Http::fake(fn () => throw new ConnectionException('DNS unavailable'));

        $payload = $this->payload();
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertOk();

        $product = Product::where('sku', 'P1')->firstOrFail();
        $this->assertSame($payload['product']['mainImage'], $product->image);
        $this->assertDatabaseHas('product_media', [
            'product_id' => $product->id,
            'role' => 'main_image',
            'url' => $payload['product']['mainImage'],
            'source' => 'pim_remote_fallback',
        ]);
    }

    public function test_rejects_unapproved_hosts_and_redirects(): void
    {
        $payload = $this->payload();
        $payload['product']['mainImage'] = 'https://storage.eigeradventure.com.evil.test/main.png';
        $payload['image']['generic'][0]['image'][0]['url'] = $payload['product']['mainImage'];
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertUnprocessable();
        Http::assertNothingSent();
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);
        $this->withToken('test')->postJson('/api/integrations/pim/product', $this->payload())->assertUnprocessable();
        Http::assertSentCount(1);
    }

    public function test_hash_named_media_checks_contents_and_reuses_local_copy(): void
    {
        Http::fake(['*' => Http::response($this->png)]);
        $url = 'http://pim.test/media/'.hash('sha256', $this->png).'.png';
        $importer = app(PimHttpMediaImporter::class);
        $importer->import($url, 'main_image');
        $importer->import($url, 'main_image');
        Http::assertSentCount(1);
        $this->expectException(ValidationException::class);
        $importer->import('http://pim.test/media/'.str_repeat('0', 64).'.png', 'main_image');
    }

    public function test_product_and_led_ambience_videos_use_separate_folders(): void
    {
        $mp4 = hex2bin('00000018667479706d703432000000006d70343269736f6d');
        Http::fake(['storage.eigeradventure.com/*' => Http::response($mp4, 200)]);
        $context = ['product_name' => 'Bag Trail 20L', 'generic_sku' => '910000001'];
        $importer = app(PimHttpMediaImporter::class);

        $productVideo = $importer->import('https://storage.eigeradventure.com/product.mp4', 'PRODUCT_IN_ACTION', $context);
        $ambienceVideo = $importer->import('https://storage.eigeradventure.com/ambience.mp4', 'LED_AMBIENCE_VIDEO', $context);

        $hash = hash('sha256', $mp4);
        $this->assertSame('/api/pim-media/products/videos/bag-trail-20l--910000001/'.$hash.'.mp4', $productVideo['url']);
        $this->assertSame('/api/pim-media/led-ambience/videos/bag-trail-20l--910000001/'.$hash.'.mp4', $ambienceVideo['url']);
        $this->get($productVideo['url'])->assertOk()->assertHeader('Content-Type', 'video/mp4');
        $this->get($ambienceVideo['url'])->assertOk()->assertHeader('Content-Type', 'video/mp4');
    }

    public function test_missing_endpoint_is_not_reported_as_missing_article(): void
    {
        Http::fake(['*' => Http::response('<html>Cannot GET</html>', 404)]);
        $this->getJson('/admin/products/pim-lookup?code=P1')->assertUnprocessable()
            ->assertJsonPath('errors.pim_code.0', 'Endpoint payload belum tersedia di server PIM. Perbarui deployment simulator TrueNAS.');
    }
}
