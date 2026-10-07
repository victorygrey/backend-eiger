<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Services\AtomProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportAtomProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_atom_product_uses_the_pim_schema_and_preserves_atom_commercial_values(): void
    {
        config(['atom.base_url' => 'https://atom.test/api/v1/app']);
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_ends_with($url, '/products/curtus')) {
                return Http::response(['data' => ['id' => '8108', 'attributes' => [
                    'skuProduct' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
                    'description' => '<p>Sepatu hiking.</p>', 'material' => 'Nubuck', 'weight' => '3000',
                    'activity' => ['id' => 7, 'name' => 'Day Hike'],
                    'category' => ['name' => 'Footwear', 'slug' => 'footwear'],
                    'subCategory' => ['name' => 'Sepatu', 'slug' => 'shoes'],
                    'type' => ['name' => 'Mid Cut Shoes'],
                    'files' => [['id' => 1, 'type' => 'image', 'url' => 'https://d1yutv2xslo29o.cloudfront.net/main.jpeg']],
                    'specifications' => [], 'technology' => [], 'performanceRating' => [],
                ]]]);
            }
            if (str_ends_with($url, '/products/curtus/variants')) {
                return Http::response(['data' => [['id' => '48498', 'attributes' => [
                    'sku' => '910012103001', 'size' => '38', 'stock' => 3,
                    'price' => ['sellingPriceNumber' => '1929000'],
                ]]]]);
            }
            if (str_ends_with($url, '/products/curtus/variant-images')) {
                return Http::response(['data' => [['id' => '256100', 'attributes' => [
                    'color' => ['name' => 'BLACK'],
                    'image' => 'https://d1yutv2xslo29o.cloudfront.net/black.jpeg',
                ]]]]);
            }
            if (str_contains($url, '/api/server/pricing_details') || str_contains($url, '/api/server/inventories/bybin')) {
                return Http::response(['data' => []]);
            }

            return Http::response([], 404);
        });

        $result = app(AtomProductImportService::class)->import([
            'sku' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
            'gender' => 'men', 'gender_source' => 'neutral_fallback',
            'product_type' => 'shoes', 'activity' => 'Day Hike',
        ], false);

        $product = Product::where('sku', '910012103')->firstOrFail();
        $this->assertSame('910012103', $result['sku']);
        $this->assertSame('MEN', $product->gender);
        $this->assertSame('Sepatu', $product->category);
        $this->assertSame(1929000.0, (float) $product->price);
        $this->assertSame(3, $product->stock);
        $this->assertSame('atom-manual', $product->pimRecord->source);
        $this->assertDatabaseHas('product_variants', [
            'product_id' => $product->id,
            'sku' => '910012103001',
            'price' => 1929000,
            'stock' => 3,
        ]);
        $this->assertDatabaseHas('product_activities', [
            'product_id' => $product->id,
            'name' => 'Day Hike',
            'is_selected' => true,
        ]);
    }

    public function test_completed_manifest_removes_one_time_scheduler_marker(): void
    {
        $root = sys_get_temp_dir().'/atom-command-'.bin2hex(random_bytes(4));
        $manifest = $root.'/manifest.json';
        $marker = $root.'/atom-import.enabled';
        File::ensureDirectoryExists($root);
        File::put($marker, 'run');
        File::put($manifest, json_encode([
            'schema_version' => 1,
            'per_type' => 50,
            'products' => [[
                'sku' => '910012103',
                'slug' => 'curtus',
                'name' => 'CURTUS',
                'gender' => 'men',
                'gender_source' => 'neutral_fallback',
                'product_type' => 'shoes',
                'activity' => 'Day Hike',
                'activity_group' => 'camping_hiking',
                'status' => 'done',
                'attempts' => 1,
            ]],
        ]));

        try {
            $this->artisan('atom:import-products', [
                '--manifest' => $manifest,
                '--completion-marker' => $marker,
            ])->assertSuccessful();

            $this->assertFileDoesNotExist($marker);
        } finally {
            File::deleteDirectory($root);
        }
    }
}
