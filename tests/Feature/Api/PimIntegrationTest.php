<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PimIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pim.legacy_http_enabled' => true, 'pim.copy_http_media' => false]);
    }

    private function payload(): array
    {
        return [
            'product' => [
                'generic' => '910012408', 'name' => 'Jacket',
                'mainImage' => 'https://example.com/main.jpg',
                'customAttributes' => [['attributeCode' => 'long_description', 'value' => 'Windproof jacket']],
                'variant' => [['sku' => '910012408001', 'name' => 'Jacket Black M']],
            ],
            'image' => ['variant' => [['sku' => '910012408001', 'image' => [
                ['type' => 'main_image', 'url' => 'https://example.com/variant.jpg'],
            ]]]],
        ];
    }

    public function test_receiver_requires_configured_token(): void
    {
        config(['pim.inbound_token' => null]);
        $this->postJson('/api/integrations/pim/product', $this->payload())->assertUnauthorized();
        config(['pim.inbound_token' => 'test-secret']);
        $this->withToken('wrong')->postJson('/api/integrations/pim/product', $this->payload())->assertUnauthorized();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_publish_upserts_variants_and_preserves_care_fields(): void
    {
        config(['pim.inbound_token' => 'test-secret']);
        $product = Product::factory()->create(['sku' => '910012408001', 'price' => 123456, 'stock' => 7, 'material' => 'Nylon']);
        for ($i = 0; $i < 2; $i++) {
            $this->withToken('test-secret')->postJson('/api/integrations/pim/product', $this->payload())
                ->assertOk()->assertJsonPath('data.synced', 1);
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', [
            'id' => $product->id, 'name' => 'Jacket Black M', 'price' => 123456, 'stock' => 7,
            'material' => 'Nylon', 'description' => 'Windproof jacket', 'image' => 'https://example.com/variant.jpg',
        ]);
        $this->assertDatabaseHas('sync_logs', ['source' => 'pim', 'status' => 'success']);
    }

    public function test_new_variant_defaults_and_failed_requests_do_not_write(): void
    {
        config(['pim.inbound_token' => 'test-secret']);
        $this->withToken('test-secret')->postJson('/api/integrations/pim/product', ['product' => []])->assertUnprocessable();
        $this->withToken('test-secret')->withHeader('X-Simulate-Atom-Failure', 'true')
            ->postJson('/api/integrations/pim/product', $this->payload())->assertStatus(500);
        $this->assertDatabaseCount('products', 0);
        $this->withHeader('X-Simulate-Atom-Failure', 'false')->postJson('/api/integrations/pim/product', $this->payload())->assertOk();
        $this->assertDatabaseHas('products', ['sku' => '910012408001', 'price' => 0, 'stock' => 0]);
    }

    public function test_cms_proxies_all_read_endpoints_and_publish_without_changing_contract(): void
    {
        config(['pim.url' => 'http://pim.test:8001/']);
        Http::preventStrayRequests();
        foreach (['publish-list' => 'articles/publish-list', 'channel-list' => 'articles/channel-list', 'history' => 'articles/list-index-publish', 'logs' => 'publish-logs'] as $endpoint => $path) {
            Http::fake(['pim.test:8001/api/'.$path.'*' => Http::response(['status' => true, 'data' => []])]);
            $this->getJson('/admin/pim/'.$endpoint.'?page=2')->assertOk()->assertJsonPath('status', true);
            Http::assertSent(fn ($request) => str_contains($request->url(), '/api/'.$path) && $request['page'] == 2);
        }
        Http::fake(['pim.test:8001/api/articles/publish' => Http::response(['status' => false, 'message' => 'Already publishing'], 409)]);
        $this->postJson('/admin/pim/publish', ['articles' => [['articles_parent' => '123']]])->assertStatus(409);
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request['articles'][0]['articles_parent'] === '123');
        $this->getJson('/admin/pim/publish')->assertStatus(405);
        $this->postJson('/admin/pim/logs')->assertStatus(405);
        $this->getJson('/admin/pim/simulator/reset')->assertNotFound();
    }

    public function test_cms_handles_unreachable_and_non_json_upstream(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Timeout'));
        $this->getJson('/admin/pim/channel-list')->assertStatus(502)->assertJsonPath('status', false);
        Http::fake(['*' => Http::response('<html>Error</html>', 500)]);
        $this->getJson('/admin/pim/channel-list')->assertStatus(502);
        $this->get('/admin/pim')->assertOk()->assertSee('PIM Integration');
    }

    public function test_cms_accepts_long_s3_urls_and_custom_atributes(): void
    {
        config(['pim.inbound_token' => 'test-secret']);
        $longUrl = 'https://pim-development-932708080162-ap-southeast-3-an.s3.ap-southeast-3.amazonaws.com/media/1787559064_14_910005551.BLK.36.JPG?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIA5SKN33IRLJFD6NHE%2F20260902%2Fap-southeast-3%2Fs3%2Faws4_request&X-Amz-Date=20260902T062314Z&X-Amz-SignedHeaders=host&X-Amz-Expires=86400&X-Amz-Signature=f35173e23bb94650b3093c0ef64c0303c247fdd318ac34c94be60e98ba45138d';

        $payload = [
            'product' => [
                'generic' => '910012408',
                'name' => 'ACROSS 1.6 WINDPROOF V3',
                'mainImage' => $longUrl,
                'customAtributes' => [
                    ['attributeCode' => 'short_description', 'value' => 'Short desc'],
                    ['attributeCode' => 'long_description', 'value' => 'Long windproof jacket desc'],
                ],
                'variant' => [
                    ['sku' => '910012408001', 'name' => 'ACROSS 1.6 WINDPROOF V3 - BLK - M'],
                ],
            ],
            'image' => [
                'generic' => [
                    [
                        'sku' => '910012408',
                        'image' => [
                            ['id' => 'abc', 'type' => 'main_image', 'url' => $longUrl, 'source' => 'PIM'],
                        ],
                    ],
                ],
                'variant' => [
                    [
                        'sku' => '910012408001',
                        'image' => [
                            ['id' => 'def', 'type' => 'main_image', 'url' => $longUrl, 'source' => 'PIM'],
                        ],
                    ],
                ],
            ],
        ];

        $this->withToken('test-secret')
            ->postJson('/api/integrations/pim/product', $payload)
            ->assertOk()
            ->assertJsonPath('data.synced', 1);

        $this->assertDatabaseHas('products', [
            'sku' => '910012408001',
            'name' => 'ACROSS 1.6 WINDPROOF V3 - BLK - M',
            'description' => 'Long windproof jacket desc',
            'image' => $longUrl,
        ]);
    }
}
