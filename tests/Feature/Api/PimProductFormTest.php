<?php
namespace Tests\Feature\Api;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
class PimProductFormTest extends TestCase
{
    use RefreshDatabase;
    private function payload(): array { return ['generic'=>'P1','name'=>'Bag','mainImage'=>'http://pim.test/media/main.jpg','weight'=>120,'variant'=>[['sku'=>'P1','name'=>'Bag','color'=>'BLACK','size'=>'22L','moq'=>'1','ecmsku'=>'P1','customAttributes'=>[]]],'customAtributes'=>[['attributeCode'=>'gender','value'=>'Pria']],'media'=>[],'technology'=>[],'activity'=>[],'specification'=>[]]; }
    public function test_lookup_reads_both_payloads_without_creating_products(): void {
        config(['pim.url'=>'http://pim.test']);
        Http::fake(['*/product-payload'=>Http::response($this->payload()),'*/image-payload'=>Http::response(['generic'=>[],'variant'=>[]])]);
        $this->getJson('/admin/products/pim-lookup?code=P1')->assertOk()->assertJsonPath('product.generic','P1');
        $this->assertDatabaseCount('products',0);Http::assertSentCount(2);
    }
    public function test_form_persists_complete_payload_and_rejects_foreign_sku(): void {
        config(['pim.copy_http_media'=>false]);
        $data=['sku'=>'P1','name'=>'Bag','price'=>99,'pim_payload_json'=>json_encode($this->payload()),'pim_image_payload_json'=>json_encode(['generic'=>[],'variant'=>[]])];
        $this->post('/admin/products',$data)->assertRedirect('/admin/products');
        $p=Product::first();$this->assertSame('P1',$p->pim_payload['generic']);$this->assertSame('Pria',$p->pim_payload['customAtributes'][0]['value']);
        $data['sku']='WRONG';$this->postJson('/admin/products',$data)->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->assertDatabaseCount('products',1);
    }
    public function test_catalog_lookup_returns_material_zone_and_scraped_article_variant(): void {
        \App\Models\Zone::create(['name' => 'Zone Tas & Aksesoris', 'code' => 'ACC']);
        config(['pim.url' => 'http://pim.test', 'services.care.url' => 'http://care.test']);
        Http::fake([
            'pim.test/*' => Http::response(['message' => 'not found'], 404),
            'care.test/api/health' => Http::response(['status' => 'ok']),
            'care.test/api/server/pricing_details*' => Http::response(['data' => [
                ['skucode' => '910004724', 'articleprice' => 699000, 'loccode' => '2022'],
                ['skucode' => '910004724001', 'articleprice' => 699000, 'loccode' => '2022'],
            ]]),
            'care.test/api/server/stocks*' => Http::response(['data' => [
                ['skucode' => '910004724', 'stock' => 8, 'loccode' => '2022'],
                ['skucode' => '910004724001', 'stock' => 8, 'loccode' => '2022'],
            ]]),
            'care.test/api/products' => Http::response(['data' => [
                ['sku' => '910004724001', 'name' => 'DENALI-NR - BLACK - ALL'],
            ]]),
        ]);
        $resp = $this->getJson('/admin/products/catalog-lookup?code=910004724');
        $resp->assertOk();
        $resp->assertJsonPath('sku', '910004724');
        $resp->assertJsonPath('name', 'DENALI-NR');
        $resp->assertJsonPath('material', 'Canvas');
        $this->assertNotNull($resp->json('zone_id'));
        $variants = $resp->json('variants');
        $this->assertNotEmpty($variants);
        foreach ($variants as $v) {
            $this->assertSame(12, strlen($v['sku']));
            $this->assertStringStartsWith('910004724', $v['sku']);
            $this->assertArrayHasKey('size', $v);
        }
    }
}
