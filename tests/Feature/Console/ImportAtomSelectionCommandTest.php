<?php

namespace Tests\Feature\Console;

use App\Models\Product;
use App\Services\AtomProductImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImportAtomSelectionCommandTest extends TestCase
{
    use RefreshDatabase;

    private function selection(): array
    {
        return [
            'sku' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
            'gender' => 'PRIA', 'gender_source' => 'website-exact-sku',
            'product_type' => 'Shoes', 'activity' => 'Day Hike',
            'resources' => [
                'detail' => ['skuProduct' => '910012103', 'slug' => 'curtus', 'name' => 'CURTUS',
                    'category' => ['name' => 'Footwear'], 'subCategory' => ['name' => 'Sepatu']],
                'variants' => [
                    ['sku' => '910012103003', 'size' => 'M', 'color' => ['name' => 'GREY'], 'stock' => 4],
                    ['sku' => '910012103017', 'size' => 'M', 'color' => ['name' => 'NAVY'], 'stock' => 2],
                    ['sku' => '910012103002', 'size' => 'L', 'color' => ['name' => 'GREY'], 'stock' => 3],
                    ['sku' => '910012103016', 'size' => 'L', 'color' => ['name' => 'NAVY'], 'stock' => 1],
                ],
                'variant_images' => [
                    ['color' => ['name' => 'GREY'], 'image' => 'https://d1yutv2xslo29o.cloudfront.net/grey.jpg'],
                    ['color' => ['name' => 'NAVY'], 'image' => 'https://d1yutv2xslo29o.cloudfront.net/navy.jpg'],
                ],
            ],
        ];
    }

    public function test_missing_only_import_preserves_existing_pim_product_and_never_calls_atom(): void
    {
        Http::preventStrayRequests();
        $product = Product::create(['sku' => '910012103', 'name' => 'PIM ORIGINAL', 'price' => 999, 'stock' => 7]);
        $product->pimRecord()->create(['generic_sku' => $product->sku, 'source' => 'pim',
            'payload_checksum' => hash('sha256', 'original'), 'received_at' => now(),
            'product_payload' => ['original' => true]]);
        $product->variants()->create(['sku' => '910012103001', 'name' => 'Original variant', 'stock' => 7]);
        $before = $product->fresh()->toArray();
        $selection = $this->selection();
        $result = app(AtomProductImportService::class)->import($selection, false, true, $selection['resources']);
        $this->assertSame('skipped_existing', $result['status']);
        $this->assertSame($before, $product->fresh()->toArray());
        $this->assertSame('pim', $product->pimRecord->source);
        $this->assertSame(1, $product->variants()->count());
        Http::assertNothingSent();
    }

    public function test_article_mismatch_is_rejected_before_any_database_write(): void
    {
        $selection = $this->selection();
        $selection['resources']['detail']['skuProduct'] = '910099999';
        try {
            app(AtomProductImportService::class)->import($selection, false, true, $selection['resources']);
            $this->fail('Wrong article must be rejected.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('products', 0);
            $this->assertDatabaseCount('product_variants', 0);
        }
    }

    public function test_archive_import_uses_verified_colors_instead_of_array_order(): void
    {
        Http::preventStrayRequests();
        $selection = $this->selection();
        app(AtomProductImportService::class)->import($selection, false, true, $selection['resources']);
        foreach (['910012103003' => 'GREY', '910012103017' => 'NAVY', '910012103002' => 'GREY', '910012103016' => 'NAVY'] as $sku => $color) {
            $this->assertDatabaseHas('product_variants', ['sku' => $sku, 'color' => $color]);
        }
        Http::assertNothingSent();
    }

    public function test_manifest_import_is_resumable_and_protected_skus_are_never_created(): void
    {
        $root = sys_get_temp_dir().'/atom-selection-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($root);
        $path = $root.'/manifest.json';
        $protected = $this->selection();
        $protected['sku'] = '980000201';
        $protected['slug'] = 'excelsior-65';
        unset($protected['resources']);
        File::put($path, json_encode(['protected_skus' => ['980000201'], 'products' => [$this->selection(), $protected]]));
        try {
            $this->artisan('atom:import-selection', ['manifest' => $path, '--dry-run' => true])->assertSuccessful();
            $this->assertDatabaseCount('products', 0);
            $this->artisan('atom:import-selection', ['manifest' => $path, '--skip-media' => true])->assertSuccessful();
            $this->artisan('atom:import-selection', ['manifest' => $path, '--skip-media' => true])->assertSuccessful();
            $this->assertDatabaseCount('products', 1);
            $this->assertDatabaseMissing('products', ['sku' => '980000201']);
            $saved = json_decode(File::get($path), true);
            $this->assertSame('imported', $saved['products'][0]['status']);
            $this->assertSame('protected', $saved['products'][1]['status']);
        } finally {
            File::deleteDirectory($root);
        }
    }

    public function test_duplicate_articles_reject_the_entire_manifest(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'atom-selection');
        File::put($path, json_encode(['products' => [$this->selection(), $this->selection()]]));
        try {
            $this->artisan('atom:import-selection', ['manifest' => $path, '--skip-media' => true])->assertFailed();
            $this->assertDatabaseCount('products', 0);
        } finally {
            File::delete($path);
        }
    }

    public function test_real_http_client_imports_from_optional_local_simulation(): void
    {
        if (! getenv('ATOM_SIMULATION_URL')) {
            $this->markTestSkipped('Set ATOM_SIMULATION_URL to run the local Postman simulation.');
        }
        config(['atom.base_url' => getenv('ATOM_SIMULATION_URL')]);
        $selection = $this->selection();
        unset($selection['resources']);
        $result = app(AtomProductImportService::class)->import($selection, false, true);
        $this->assertSame('imported', $result['status']);
        $this->assertDatabaseHas('products', ['sku' => '910012103', 'name' => 'CURTUS']);
        $this->assertDatabaseHas('product_variants', ['sku' => '910012103001', 'color' => 'BLACK']);
    }
}
