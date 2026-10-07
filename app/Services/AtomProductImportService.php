<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Support\Facades\DB;

class AtomProductImportService
{
    public function __construct(
        private readonly AtomCatalogClient $atom,
        private readonly AtomProductPayloadMapper $payloadMapper,
        private readonly PimPayload $pimPayload,
        private readonly PimCareProductMapper $careMapper,
        private readonly PimProductDataStore $dataStore,
    ) {}

    /** @param array<string, mixed> $selection */
    public function import(array $selection, bool $downloadMedia = true): array
    {
        $slug = (string) $selection['slug'];
        $mapped = $this->payloadMapper->map(
            $selection,
            $this->atom->product($slug),
            $this->atom->variants($slug),
            $this->atom->variantImages($slug),
        );
        $data = $this->pimPayload->validate($mapped);
        $genericMedia = ['pim_media' => [], 'image' => $data['product']['mainImage'] ?? null];

        if ($downloadMedia) {
            // The ATOM command is explicitly an archive import, so it must not
            // inherit a runtime setting that only keeps remote PIM URLs.
            config(['pim.copy_http_media' => true]);
            $genericMedia = $this->pimPayload->mediaValues(
                $data['product'], $data['image'], $data['product']['generic']
            );
        }

        // ATOM already supplies retail price and stock. Avoid thousands of CARE
        // lookups during this temporary bulk import; regular PIM ingest still
        // enriches from CARE through the default mapper path.
        $catalog = $this->careMapper->map($data['product'], $data['image'], useCare: false);
        $fallbackPrice = collect($catalog['variants'])->pluck('price')->filter(fn ($value) => $value > 0)->min() ?? 0;
        $fallbackStock = collect($catalog['variants'])->sum('stock');
        $localizedBySource = collect($genericMedia['pim_media'] ?? [])
            ->filter(fn ($row) => is_string($row['source_url'] ?? null) && is_string($row['url'] ?? null))
            ->pluck('url', 'source_url');

        return DB::transaction(function () use ($data, $catalog, $genericMedia, $localizedBySource, $fallbackPrice, $fallbackStock) {
            $detail = $data['product'];
            $parent = Product::updateOrCreate(['sku' => $detail['generic']], [
                'name' => $catalog['name'] ?: $detail['name'],
                'zone_id' => $catalog['zone_id'],
                'material' => $catalog['material'],
                'description' => $catalog['description'],
                'price' => $catalog['care_found'] ? $catalog['price'] : $fallbackPrice,
                'stock' => $catalog['care_found'] ? $catalog['stock'] : $fallbackStock,
                'pim_catalog_active' => true,
                'image' => $genericMedia['image'] ?? $detail['mainImage'] ?? null,
                'care_synced_at' => $catalog['care_found'] ? now() : null,
            ]);

            $syncedSkus = [];
            foreach ($catalog['variants'] as $variant) {
                $syncedSkus[] = $variant['sku'];
                $saved = $parent->variants()->updateOrCreate(['sku' => $variant['sku']], [
                    'name' => $variant['name'],
                    'color' => $variant['color'] ?? null,
                    'size' => $variant['size'] ?? null,
                    'ecmsku' => $variant['ecmsku'] ?? null,
                    'moq' => $variant['moq'] ?? null,
                    'price' => $variant['price'] ?? 0,
                    'stock' => $variant['stock'] ?? 0,
                    'image' => $localizedBySource->get($variant['image'] ?? '')
                        ?? $genericMedia['image']
                        ?? $variant['image']
                        ?? null,
                ]);
                $this->dataStore->replaceVariantAttributes($saved, $variant['customAttributes'] ?? []);
            }

            if ($syncedSkus !== []) {
                $parent->variants()->whereNotIn('sku', $syncedSkus)->delete();
            }

            $this->dataStore->replace(
                $parent,
                $detail,
                $data['image'],
                $genericMedia['pim_media'],
                'atom-app-v1',
                'atom-manual',
                [],
            );

            SyncLog::create([
                'source' => 'atom-manual',
                'status' => 'success',
                'message' => sprintf(
                    'ATOM article %s: %d varian dan %d media berhasil diimpor%s.',
                    $detail['generic'],
                    count($catalog['variants']),
                    count($genericMedia['pim_media']),
                    $catalog['care_found'] ? ' serta diperkaya CARE' : ''
                ),
                'synced_at' => now(),
            ]);

            return [
                'product_id' => $parent->id,
                'sku' => $parent->sku,
                'variants' => count($catalog['variants']),
                'media' => count($genericMedia['pim_media']),
                'care_enriched' => $catalog['care_found'],
            ];
        });
    }
}
