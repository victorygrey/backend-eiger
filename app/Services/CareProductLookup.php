<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class CareProductLookup
{
    /**
     * Read one article and all of its sellable variants from CARE.
     *
     * @return array{found: bool, price: float, stock: int, variants: array<int, array<string, mixed>>}
     */
    public function get(string $articleSku): array
    {
        $articleSku = trim($articleSku);
        $baseUrl = app(CareSyncService::class)->getBaseUrl();
        $storeCode = (string) config('services.care.store_code', '2022');
        $timeout = (int) config('services.care.timeout', 10);
        $headers = ['Accept' => 'application/json'];

        if ($serverKey = config('services.care.server_key')) {
            $headers['x-server-key'] = $serverKey;
        }

        $pricing = Http::timeout($timeout)->withHeaders($headers)->acceptJson()
            ->get(rtrim($baseUrl, '/').'/api/server/pricing_details', [
                'filter' => ['loccode' => $storeCode],
            ]);
        $stocks = Http::timeout($timeout)->withHeaders($headers)->acceptJson()
            ->get(rtrim($baseUrl, '/').'/api/server/stocks', [
                'filter' => ['loccode' => $storeCode],
            ]);

        if (! $pricing->successful() || ! $stocks->successful()) {
            throw new \RuntimeException(sprintf(
                'CARE price/stock lookup failed (HTTP %d/%d).',
                $pricing->status(),
                $stocks->status()
            ));
        }

        $catalogNames = [];
        try {
            $catalog = Http::timeout($timeout)->withHeaders($headers)->acceptJson()
                ->get(rtrim($baseUrl, '/').'/api/products');
            if ($catalog->successful()) {
                $catalogRows = $catalog->json('data');
                foreach (is_array($catalogRows) ? $catalogRows : [] as $item) {
                    $sku = (string) ($item['sku'] ?? $item['skucode'] ?? '');
                    if ($this->belongsToArticle($sku, $articleSku)) {
                        $catalogNames[$sku] = (string) ($item['name'] ?? '');
                    }
                }
            }
        } catch (\Throwable) {
            // Names are optional. Official CARE price and stock remain authoritative.
        }

        $items = [];
        $pricingRows = $pricing->json('data');
        foreach (is_array($pricingRows) ? $pricingRows : [] as $row) {
            $sku = (string) ($row['skucode'] ?? $row['sku'] ?? '');
            if (! $this->belongsToArticle($sku, $articleSku) || ! $this->matchesStore($row, $storeCode)) {
                continue;
            }

            // Prefer the store-specific row when CARE also returns a global price row.
            $isStoreSpecific = (string) ($row['loccode'] ?? '') === $storeCode;
            if (! isset($items[$sku]) || $isStoreSpecific || empty($items[$sku]['store_specific_price'])) {
                $items[$sku] = array_merge($items[$sku] ?? [], [
                    'sku' => $sku,
                    'price' => (float) ($row['articleprice'] ?? $row['price'] ?? 0),
                    'store_specific_price' => $isStoreSpecific,
                ]);
            }
        }

        $stockRows = $stocks->json('data');
        foreach (is_array($stockRows) ? $stockRows : [] as $row) {
            $sku = (string) ($row['skucode'] ?? $row['sku'] ?? '');
            if (! $this->belongsToArticle($sku, $articleSku) || ! $this->matchesStore($row, $storeCode)) {
                continue;
            }
            $items[$sku] = array_merge($items[$sku] ?? ['sku' => $sku], [
                'stock' => (int) ($row['available_stock'] ?? $row['stock'] ?? 0),
            ]);
        }

        $parent = $items[$articleSku] ?? [];
        $variants = [];
        foreach ($items as $sku => $item) {
            if (strlen($sku) !== 12 || ! ctype_digit($sku)) {
                continue;
            }

            $name = $catalogNames[$sku] ?? '';
            [$color, $size] = $this->parseVariantName($name);
            $variants[] = [
                'sku' => $sku,
                'name' => $name ?: ('Variant '.$sku),
                'color' => $color,
                'size' => $size,
                'price' => (float) ($item['price'] ?? $parent['price'] ?? 0),
                'stock' => (int) ($item['stock'] ?? 0),
            ];
        }

        usort($variants, fn (array $a, array $b) => strcmp($a['sku'], $b['sku']));
        $variantStock = array_sum(array_column($variants, 'stock'));
        $variantPrices = array_values(array_filter(
            array_column($variants, 'price'),
            fn ($price) => (float) $price > 0
        ));

        return [
            'found' => isset($items[$articleSku]) || $variants !== [],
            'price' => (float) ($parent['price'] ?? ($variantPrices ? min($variantPrices) : 0)),
            'stock' => $variants !== [] ? $variantStock : (int) ($parent['stock'] ?? 0),
            'variants' => $variants,
        ];
    }

    private function belongsToArticle(string $sku, string $articleSku): bool
    {
        return $sku === $articleSku
            || (strlen($sku) === 12 && substr($sku, 0, 9) === $articleSku);
    }

    private function matchesStore(array $row, string $storeCode): bool
    {
        $location = $row['loccode'] ?? null;
        return $location === null || $location === '' || (string) $location === $storeCode;
    }

    /** @return array{0: string, 1: string} */
    private function parseVariantName(string $name): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(' - ', $name)), 'strlen'));
        if (count($parts) < 3) {
            return ['', ''];
        }

        return [$parts[count($parts) - 2], $parts[count($parts) - 1]];
    }
}
