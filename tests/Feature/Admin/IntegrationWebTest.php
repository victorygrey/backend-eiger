<?php

namespace Tests\Feature\Admin;

use App\Models\PimApiToken;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_unified_integrations_page_loads_successfully(): void
    {
        Http::fake([
            '*/api/server/pricing_details*' => Http::response(['data' => []], 200),
            '*/api/server/inventories/bybin*' => Http::response(['data' => []], 200),
            '*/api/articles/channel-list*' => Http::response([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'pagination' => ['total' => 60],
                ],
            ], 200),
        ]);

        $response = $this->get(route('admin.integrations.index'));

        $response->assertStatus(200);
        $response->assertSee('Integrasi Sistem PIM & CARE OMNI', false);
        $response->assertSee('Sinkronkan Keduanya Sekarang');
        $response->assertSee('1. Master Katalog PIM');
        $response->assertSee('2. Ritel & Stok CARE OMNI', false);
        $response->assertSee('Jadwal Auto-Sync');
        $response->assertSee('Tiap 10 Menit');
    }

    public function test_sync_all_triggers_both_pim_and_care(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
        $storedImage = '/api/pim-media/products/photos/tourer-wander-11-22l-1a--910009029/'.hash('sha256', $png).'.png';
        Http::fake([
            '*/api/ui/articles*' => Http::response([
                'data' => [['sap_id' => '910009029']],
                'pagination' => ['total_pages' => 1],
            ], 200),
            '*/api/articles/910009029/product-payload' => Http::response([
                'generic' => '910009029',
                'name' => 'TOURER WANDER 1.1 22L 1A',
                'mainImage' => 'https://storage.eigeradventure.com/cover.jpg',
                'variant' => [['sku' => '910009029', 'name' => 'TOURER WANDER 1.1 22L 1A']],
            ], 200),
            '*/api/articles/910009029/image-payload' => Http::response([
                'generic' => [], 'variant' => [],
            ], 200),
            'storage.eigeradventure.com/*' => Http::response($png, 200),
            '*/api/server/pricing_details*' => Http::response([
                'data' => [
                    [
                        'id' => 1,
                        'skucode' => '910009029001',
                        'articleprice' => '250000.00',
                        'loccode' => '2022',
                    ],
                ],
            ], 200),
            '*/api/server/inventories/bybin*' => Http::response([
                'data' => [
                    [
                        'sku_code' => '910009029001',
                        'sku_generic_code' => '910009029',
                        'sku_name' => 'TOURER WANDER - BLACK - ALL',
                        'available_qty' => 15,
                        'bin_status' => 'active',
                        'bin_category' => 'saleable goods',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->post(route('admin.integrations.sync-all'));

        $response->assertRedirect(route('admin.integrations.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('products', [
            'sku' => '910009029',
            'name' => 'TOURER WANDER 1.1 22L 1A',
            'image' => $storedImage,
            'price' => 250000,
            'stock' => 15,
        ]);
        $this->assertSame($storedImage, Product::where('sku', '910009029')->value('image'));

        $this->assertDatabaseHas('product_variants', [
            'sku' => '910009029001',
            'price' => 250000,
            'stock' => 15,
        ]);
    }

    public function test_individual_pim_and_care_sync_endpoints(): void
    {
        Http::fake([
            '*/api/articles/publish-list*' => Http::response([
                'status' => true,
                'message' => 'Success',
                'data' => [
                    'data' => [],
                    'pagination' => ['total' => 0],
                ],
            ], 200),
            '*/api/server/pricing_details*' => Http::response([
                'data' => [],
            ], 200),
            '*/api/server/inventories/bybin*' => Http::response([
                'data' => [],
            ], 200),
        ]);

        $responsePim = $this->post(route('admin.integrations.sync-pim'));
        $responsePim->assertRedirect(route('admin.integrations.index'));

        $responseCare = $this->post(route('admin.integrations.sync-care'));
        $responseCare->assertRedirect(route('admin.integrations.index'));
    }

    public function test_superadmin_can_issue_two_hour_pim_token(): void
    {
        $response = $this->post(route('admin.integrations.pim-token.issue'), ['name' => 'EIGER-PIM']);
        $response->assertRedirect(route('admin.integrations.index'))->assertSessionHas('pim_token');
        $issued = session('pim_token');
        $this->assertStringStartsWith('pim_', $issued['token']);
        $this->assertDatabaseHas('pim_api_tokens', [
            'name' => 'EIGER-PIM',
            'token_hash' => hash('sha256', $issued['token']),
        ]);
    }

    public function test_superadmin_can_issue_token_until_requested_datetime_and_revoke_previous_one(): void
    {
        $first = $this->post(route('admin.integrations.pim-token.issue'), [
            'name' => 'EIGER-PIM',
            'expires_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
        ]);
        $firstToken = $first->getSession()->get('pim_token.token');

        $target = now()->addDay()->startOfDay();
        $second = $this->post(route('admin.integrations.pim-token.issue'), [
            'name' => 'EIGER-PIM',
            'expires_at' => $target->format('Y-m-d H:i:s'),
        ]);
        $second->assertRedirect(route('admin.integrations.index'));
        $secondToken = $second->getSession()->get('pim_token');

        $this->assertSame($target->toIso8601String(), $secondToken['expires_at']);
        $this->assertDatabaseHas('pim_api_tokens', [
            'token_hash' => hash('sha256', $firstToken),
        ]);
        $this->assertNotNull(PimApiToken::where('token_hash', hash('sha256', $firstToken))->value('revoked_at'));
        $this->assertDatabaseHas('pim_api_tokens', [
            'token_hash' => hash('sha256', $secondToken['token']),
            'revoked_at' => null,
        ]);
    }
}
