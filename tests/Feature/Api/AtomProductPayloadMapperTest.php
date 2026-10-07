<?php

namespace Tests\Feature\Api;

use App\Services\AtomProductPayloadMapper;
use App\Services\PimPayload;
use Tests\TestCase;

class AtomProductPayloadMapperTest extends TestCase
{
    public function test_atom_response_is_mapped_to_the_existing_pim_contract(): void
    {
        $payload = app(AtomProductPayloadMapper::class)->map(
            [
                'sku' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
                'gender' => 'men', 'gender_source' => 'neutral_fallback',
                'product_type' => 'shoes', 'activity' => 'Day Hike',
            ],
            [
                'skuProduct' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
                'description' => '<p>Sepatu hiking.</p>', 'material' => '<p>Nubuck</p>', 'weight' => '3000',
                'activity' => ['id' => 7, 'name' => 'Day Hike'],
                'category' => ['name' => 'Footwear', 'slug' => 'footwear'],
                'subCategory' => ['name' => 'Sepatu', 'slug' => 'shoes'],
                'type' => ['name' => 'Mid Cut Shoes'],
                'files' => [['id' => 1, 'type' => 'image', 'url' => 'https://d1yutv2xslo29o.cloudfront.net/main.jpeg']],
                'specifications' => [['code' => 'PRODUCT_WEIGHT', 'name' => 'Product Weight', 'value' => '3000']],
            ],
            [[
                'atom_id' => '48498', 'sku' => '910012103001', 'size' => '38', 'stock' => 3,
                'price' => ['sellingPriceNumber' => '1929000'],
            ]],
            [[
                'atom_id' => '256100', 'color' => ['name' => 'BLACK'],
                'image' => 'https://d1yutv2xslo29o.cloudfront.net/black.jpeg',
            ]],
        );

        $validated = app(PimPayload::class)->validate($payload);

        $this->assertSame('910012103', $validated['product']['generic']);
        $this->assertSame('910012103001', $validated['product']['variant'][0]['sku']);
        $this->assertSame(1929000.0, $validated['product']['variant'][0]['price']);
        $this->assertSame(3, $validated['product']['variant'][0]['stock']);
        $this->assertSame('BLACK', $validated['product']['variant'][0]['color']);
        $this->assertSame('https://d1yutv2xslo29o.cloudfront.net/main.jpeg', $validated['product']['mainImage']);
        $this->assertSame('Day Hike', collect($validated['product']['customAtributes'])
            ->firstWhere('attributeCode', 'activity')['value']);
    }
}
