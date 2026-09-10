<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\PimHttpMediaImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
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
            'technology' => [['id' => 'T1', 'name' => 'Technology from PIM']],
            'activity' => [['name' => 'Hiking', 'rating' => '4']],
            'specification' => [['code' => 'PRODUCT_WEIGHT', 'value' => '725']]],
            'image' => ['generic' => [], 'variant' => []]];
    }

    public function test_publish_retains_full_contract_and_core_api_exposes_it(): void
    {
        Http::fake(['storage.eigeradventure.com/*' => Http::response($this->png, 200)]);
        $existing = Product::factory()->create(['sku' => 'P1-M', 'price' => 250000, 'stock' => 5]);
        $payload = $this->payload();
        $this->withToken('test')->postJson('/api/integrations/pim/product', $payload)->assertOk();
        $saved = $existing->fresh();
        $this->assertSame($payload['product'], $saved->pim_payload);
        $this->assertSame($payload['image'], $saved->pim_image_payload);
        $this->assertEquals(250000, $saved->price);
        $this->assertSame(5, $saved->stock);
        $this->assertSame('/api/pim-media/'.hash('sha256', $this->png).'.png', $saved->image);
        $this->getJson('/api/products/'.$saved->id)->assertOk()
            ->assertJsonPath('data.pim_payload.weight', 725)
            ->assertJsonPath('data.pim_payload.variant.0.size', 'M')
            ->assertJsonPath('data.pim_payload.technology.0.id', 'T1');
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
        $this->assertCount(1, $saved->pim_media);
        $this->assertFileExists($this->mediaDir.'/'.hash('sha256', $this->png).'.png');
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

    public function test_rejects_unapproved_hosts_and_redirects(): void
    {
        $payload = $this->payload();
        $payload['product']['mainImage'] = 'https://storage.eigeradventure.com.evil.test/main.png';
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

    public function test_missing_endpoint_is_not_reported_as_missing_article(): void
    {
        Http::fake(['*' => Http::response('<html>Cannot GET</html>', 404)]);
        $this->getJson('/admin/products/pim-lookup?code=P1')->assertUnprocessable()
            ->assertJsonPath('errors.pim_code.0', 'Endpoint payload belum tersedia di server PIM. Perbarui deployment simulator TrueNAS.');
    }
}
