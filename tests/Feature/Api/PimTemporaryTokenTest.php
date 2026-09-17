<?php

namespace Tests\Feature\Api;

use App\Services\PimInboundTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PimTemporaryTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_bearer_token_stops_working_after_two_hours(): void
    {
        Carbon::setTestNow('2026-09-17 12:00:00');
        config([
            'pim.legacy_http_enabled' => true,
            'pim.allow_static_inbound_token' => false,
            'pim.copy_http_media' => false,
            'services.care.master_url' => 'http://care.test',
            'services.care.wms_url' => 'http://care.test',
        ]);
        Http::fake([
            'care.test/api/server/pricing_details*' => Http::response(['data' => []]),
            'care.test/api/server/inventories/bybin*' => Http::response(['data' => []]),
        ]);
        $issued = app(PimInboundTokenService::class)->issue('EIGER integration test', 120);
        $payload = [
            'product' => [
                'generic' => '910000001', 'name' => 'Test Product',
                'variant' => [['sku' => '910000001001', 'name' => 'Test Product - BLACK - M']],
            ],
            'image' => ['generic' => [], 'variant' => []],
        ];

        $this->withToken($issued['token'])->postJson('/api/integrations/pim/product', $payload)->assertOk();
        $this->assertDatabaseHas('pim_api_tokens', ['name' => 'EIGER integration test']);
        $this->assertDatabaseMissing('pim_api_tokens', ['token_hash' => $issued['token']]);

        Carbon::setTestNow('2026-09-17 14:00:01');
        $this->withToken($issued['token'])->postJson('/api/integrations/pim/product', $payload)->assertUnauthorized();
    }
}
