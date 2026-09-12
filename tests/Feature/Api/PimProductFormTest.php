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
    public function test_catalog_lookup_returns_material_zone_and_12digit_variants(): void {
        \App\Models\Zone::create(['name' => 'Zone Tas & Aksesoris', 'code' => 'ACC']);
        $resp = $this->getJson('/admin/products/catalog-lookup?code=910004724');
        $resp->assertOk();
        $resp->assertJsonPath('sku', '910004724');
        $resp->assertJsonPath('name', 'DENALI-NR');
        $resp->assertJsonPath('material', 'Canvas');
        $this->assertNotNull($resp->json('zone_id'));
        $variants = $resp->json('variants');
        $this->assertNotEmpty($variants);
        foreach ($variants as $v) {
            $this->assertSame(12, strlen($v['sku']), "SKU {$v['sku']} must be 12 digits");
            $this->assertFalse(str_contains($v['size'], ','), "Size {$v['size']} must not contain comma");
        }
    }
}
