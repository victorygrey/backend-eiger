<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PimCatalogSync
{
    public function run(string $source = 'web'): array
    {
        $base = rtrim(config('pim.url'), '/');
        $page = 1;
        $codes = [];
        try {
        do {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(config('pim.timeout'))
                ->get($base.'/api/ui/articles', ['page' => $page, 'limit' => 100]);
            $response->throw();
            foreach ($response->json('data') ?? [] as $article) {
                if (!empty($article['sap_id'])) $codes[] = (string) $article['sap_id'];
            }
            $lastPage = (int) ($response->json('pagination.total_pages') ?? 1);
            $page++;
        } while ($page <= $lastPage);
        } catch (\Throwable $error) {
            $message = 'Daftar artikel PIM tidak dapat dibaca: '.$error->getMessage();
            SyncLog::create(['source' => 'pim-'.$source, 'status' => 'failed', 'message' => $message, 'synced_at' => now()]);
            return ['success' => false, 'count' => 0, 'synced' => 0, 'variants' => 0,
                'failed' => [$message], 'message' => $message];
        }
        if (!$codes) {
            return ['success' => false, 'count' => 0, 'synced' => 0, 'variants' => 0,
                'failed' => ['Katalog PIM kosong.'], 'message' => 'Katalog PIM kosong; tidak ada data CMS yang diubah.'];
        }

        $result = ['success' => true, 'count' => count($codes), 'synced' => 0, 'variants' => 0, 'failed' => []];
        $activeSkus = [];
        foreach (array_unique($codes) as $code) {
            try {
                $payload = app(PimProductLookup::class)->get($code);
                $detail = $payload['product'];
                $image = $payload['image'];
                $assets = app(PimPayload::class);
                $perSku = [];
                foreach ($detail['variant'] as $variant) {
                    // Manual catalog synchronization retains source media references.
                    // Existing local media stays usable when a remote PIM URL has expired.
                    $perSku[$variant['sku']] = $this->mediaReferences($assets, $detail, $image, $variant['sku']);
                }
                $parentMedia = $this->mediaReferences($assets, $detail, $image, $detail['generic']);
                DB::transaction(function () use ($detail, $image, $perSku, $parentMedia) {
                    $attributes = collect($detail['customAtributes'] ?? [])->pluck('value', 'attributeCode');
                    $description = $attributes->get('long_description') ?: $attributes->get('short_description');
                    $parent = Product::firstOrNew(['sku' => $detail['generic']]);
                    $parent->name = $detail['name'];
                    $parent->pim_payload = $detail;
                    $parent->pim_image_payload = $image;
                    $parent->pim_catalog_active = true;
                    $parent->pim_media = $parentMedia['pim_media'];
                    if ($description !== null) $parent->description = $description;
                    if ($attributes->has('material')) $parent->material = $attributes->get('material');
                    $this->setImage($parent, $parentMedia['image']);
                    $parent->save();

                    foreach ($detail['variant'] as $variant) {
                        $product = Product::firstOrNew(['sku' => $variant['sku']]);
                        $product->name = $variant['name'];
                        $product->pim_payload = $detail;
                        $product->pim_image_payload = $image;
                        $product->pim_catalog_active = true;
                        $product->pim_media = $perSku[$variant['sku']]['pim_media'];
                        if ($description !== null) $product->description = $description;
                        $this->setImage($product, $perSku[$variant['sku']]['image']);
                        $product->save();
                        if ($variant['sku'] === $detail['generic']) {
                            // Scraped catalogs have no child SKU; the generic row is the parent,
                            // while real 12-digit CARE variants remain in this dropdown.
                            ProductVariant::where('product_id', $parent->id)
                                ->where('sku', $parent->sku)->delete();
                            if ($parentMedia['image']) {
                                ProductVariant::where('product_id', $parent->id)
                                    ->where('sku', '!=', $parent->sku)
                                    ->whereNull('image')->update(['image' => $parentMedia['image']]);
                            }
                            continue;
                        }
                        ProductVariant::updateOrCreate(['sku' => $variant['sku']], [
                            'product_id' => $parent->id,
                            'name' => $variant['name'],
                            'color' => $variant['color'] ?? null,
                            'size' => $variant['size'] ?? null,
                            'ecmsku' => $variant['ecmsku'] ?? null,
                            'moq' => $variant['moq'] ?? null,
                            'custom_attributes' => $variant['customAttributes'] ?? [],
                            'image' => $perSku[$variant['sku']]['image'],
                        ]);
                    }
                });
                $result['synced']++;
                $result['variants'] += count($detail['variant']);
                $activeSkus[] = $detail['generic'];
                foreach ($detail['variant'] as $variant) $activeSkus[] = $variant['sku'];
            } catch (\Throwable $error) {
                $result['failed'][] = $code.': '.$error->getMessage();
            }
        }
        $result['success'] = count($result['failed']) === 0;
        if ($result['success']) {
            Product::whereNotNull('pim_payload')->whereNotIn('sku', array_unique($activeSkus))
                ->update(['pim_catalog_active' => false]);
        }
        $result['message'] = sprintf('%d/%d artikel dan %d varian PIM tersinkronisasi; %d gagal.',
            $result['synced'], $result['count'], $result['variants'], count($result['failed']));
        SyncLog::create(['source' => 'pim-'.$source, 'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'], 'synced_at' => now()]);
        return $result;
    }

    private function mediaReferences(PimPayload $assets, array $product, array $image, string $sku): array
    {
        $media = [];
        foreach ($assets->assets($product, $image, $sku) as $asset) {
            $role = $asset['type'] === 'main_image' ? 'main_image' : 'gallery';
            $entry = ['url' => $asset['url'], 'role' => $role,
                'source' => $asset['source'] ?? 'PIM', 'sku' => $sku];
            if (config('pim.copy_http_media')
                && str_starts_with($asset['url'], rtrim(config('pim.url'), '/').'/media/')) {
                $entry = array_merge($entry,
                    app(PimHttpMediaImporter::class)->import($asset['url'], $role),
                    ['source_url' => $asset['url']]);
            }
            $media[] = $entry;
        }
        $media = array_merge($media, $assets->supplementalMedia($product));
        return ['pim_media' => $media,
            'image' => collect($media)->firstWhere('role', 'main_image')['url'] ?? ($media[0]['url'] ?? null)];
    }

    private function setImage(Product $product, ?string $source): void
    {
        if (!$source) return;
        if ($product->image && str_starts_with($product->image, '/api/pim-media/')) return;
        $product->image = $source;
    }
}
