<?php

namespace Tests\Feature\Api;

use App\Models\LedAmbienceItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RfidTag;
use App\Models\TableExpeditionItem;
use App\Models\Tablet;
use App\Services\PimProductDataStore;
use Database\Seeders\AtomMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceMasterDataTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_device_api_receives_translated_product_and_variant_custom_attributes(): void
    {
        $this->seed(AtomMasterDataSeeder::class);

        $product = Product::factory()->create([
            'sku' => '910009901',
            'name' => 'Coded Device Product',
            'ai_fit_and_go_active' => true,
            'interactive_tablet_active' => true,
            'pim_catalog_active' => true,
            'is_discontinued' => false,
        ]);

        app(PimProductDataStore::class)->replace($product, [
            'generic' => '910009901',
            'name' => 'Coded Device Product',
            'variant' => [],
            'customAtributes' => [
                ['attributeCode' => 'categoryCode', 'value' => 'F'],
                ['attributeCode' => 'subCategoryCode', 'value' => 'F01'],
                ['attributeCode' => 'activity', 'value' => 'A03018'],
                ['attributeCode' => 'waterproof', 'value' => 'Yes'],
            ],
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => '910009901001',
            'name' => 'Black M',
            'color' => 'Black',
            'size' => 'M',
            'price' => 899000,
            'stock' => 5,
        ]);
        $variant->custom_attributes = [
            ['attributeCode' => 'activityCode', 'value' => 'A03018'],
        ];
        $variant->save();

        RfidTag::create([
            'uid' => 'E28011606000020499010001',
            'product_id' => $product->id,
        ]);
        LedAmbienceItem::create([
            'rfid_tag' => 'E28011606000020499010001',
            'product_id' => $product->id,
            'is_active' => true,
        ]);
        TableExpeditionItem::create([
            'rfid_tag' => 'E28011606000020499010001',
            'product_id' => $product->id,
            'is_active' => true,
        ]);

        $tabletToken = 'tablet-device-token';
        Tablet::create([
            'slug' => 'master-data-tablet',
            'name' => 'Master Data Tablet',
            'featured_product_id' => $product->id,
            'activation_code_hash' => Hash::make('MASTER-DATA'),
            'device_token_hash' => Hash::make($tabletToken),
            'is_active' => true,
        ]);

        $fitAndGo = $this->getJson('/api/v1/fit-and-go/products/'.$product->id)
            ->assertOk()
            ->json('data');

        $tablet = $this->withToken($tabletToken)
            ->getJson('/api/tablets/master-data-tablet/display')
            ->assertOk()
            ->json('featured');

        $ledAmbience = $this->postJson('/api/v1/led-ambience/trigger', [
            'rfid_tag' => 'E28011606000020499010001',
        ])->assertOk()->json('data.product');

        $tableExpedition = $this->postJson('/api/v1/table-expedition/scan', [
            'rfid' => 'E28011606000020499010001',
        ])->assertOk()->json('data.product');

        foreach ([$fitAndGo, $tablet, $ledAmbience, $tableExpedition] as $payload) {
            $attributes = collect($payload['custom_attributes'])->keyBy('attributeCode');

            $this->assertSame('Footwear', $attributes['categoryCode']['value']);
            $this->assertSame('Sepatu', $attributes['subCategoryCode']['value']);
            $this->assertSame('Hiking', $attributes['activity']['value']);
            $this->assertSame('A03018', $attributes['activity']['master_values'][0]['code']);
            $this->assertSame('Camping & Hiking', $attributes['activity']['master_values'][0]['parent']);
            $this->assertSame('Yes', $attributes['waterproof']['value']);
            $this->assertArrayNotHasKey('master_values', $attributes['waterproof']);
            $this->assertSame('Hiking', $payload['variants'][0]['custom_attributes'][0]['value']);
        }
    }
}
