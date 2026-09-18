<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareOmniClient
{
    public function masterUrl(): string
    {
        return rtrim((string) config('services.care.master_url'), '/');
    }

    public function wmsUrl(): string
    {
        return rtrim((string) config('services.care.wms_url'), '/');
    }

    /**
     * Read CARE commercial data for one generic article.
     *
     * @param  array<int, string>  $variantSkus
     * @return array{found: bool, price: float, stock: int, variants: array<int, array<string, mixed>>}
     */
    public function article(string $articleSku, array $variantSkus = []): array
    {
        $articleSku = trim($articleSku);
        $variantSkus = array_values(array_unique(array_filter(
            array_map('trim', $variantSkus),
            fn (string $sku) => strlen($sku) === 12 && ctype_digit($sku) && str_starts_with($sku, $articleSku),
        )));

        $priceRows = $this->pricingRows($articleSku);
        $articlePrice = $this->preferredPrice($priceRows);
        $inventoryRows = [];

        if ($variantSkus !== []) {
            foreach ($variantSkus as $sku) {
                try {
                    array_push($inventoryRows, ...$this->inventoryRows($sku));
                } catch (\Throwable $error) {
                    Log::warning('CARE WMS lookup failed; retaining the price result and zero stock', [
                        'sku' => $sku,
                        'error' => $error->getMessage(),
                    ]);
                }
                array_push($priceRows, ...$this->pricingRows($sku));
            }
        } else {
            // Some PIM payloads only contain article-level color/size metadata.
            // CARE WMS exposes sellable 12-digit SKUs, so use the store catalog
            // as a discovery fallback and retain only this generic article.
            try {
                $inventoryRows = array_values(array_filter(
                    $this->allInventoryRows(),
                    fn (array $row) => (string) ($row['sku_generic_code'] ?? '') === $articleSku,
                ));
            } catch (\Throwable $error) {
                Log::warning('CARE WMS article discovery failed; retaining the price result and zero stock', [
                    'sku' => $articleSku,
                    'error' => $error->getMessage(),
                ]);
            }
        }

        $stockBySku = $this->aggregateInventory($inventoryRows);
        $priceBySku = $this->groupPrices($priceRows);
        $variants = [];

        foreach ($stockBySku as $sku => $stock) {
            $sku = (string) $sku;
            if (strlen($sku) !== 12 || ! ctype_digit($sku) || ! str_starts_with($sku, $articleSku)) {
                continue;
            }

            [$color, $size] = $this->parseVariantName($stock['name']);
            $variants[] = [
                'sku' => $sku,
                'name' => $stock['name'] ?: ('Variant '.$sku),
                'color' => $color,
                'size' => $size,
                'price' => $priceBySku[$sku] ?? $articlePrice,
                'stock' => $stock['stock'],
            ];
        }

        // Keep known PIM variants even when a store currently has no WMS row.
        foreach ($variantSkus as $sku) {
            if (collect($variants)->contains('sku', $sku)) {
                continue;
            }
            $variants[] = [
                'sku' => $sku,
                'name' => '',
                'color' => '',
                'size' => '',
                'price' => $priceBySku[$sku] ?? $articlePrice,
                'stock' => 0,
            ];
        }

        usort($variants, fn (array $a, array $b) => strcmp($a['sku'], $b['sku']));

        return [
            'found' => $priceRows !== [] || $inventoryRows !== [],
            'price' => $articlePrice,
            'stock' => array_sum(array_column($variants, 'stock')),
            'variants' => $variants,
        ];
    }

    /** @return array<int, array{sku: string, price: ?float, stock: int, name: ?string}> */
    public function allItems(): array
    {
        $pricing = $this->allPricingRows();
        $inventory = $this->allInventoryRows();
        $prices = $this->groupPrices($pricing);
        $stocks = $this->aggregateInventory($inventory);
        $items = [];

        foreach ($prices as $sku => $price) {
            $items[$sku] = ['sku' => $sku, 'price' => $price, 'stock' => 0, 'name' => null];
        }
        foreach ($stocks as $sku => $stock) {
            $items[$sku] = array_merge($items[$sku] ?? [
                'sku' => $sku,
                'price' => null,
            ], [
                'stock' => $stock['stock'],
                'name' => $stock['name'] ?: null,
            ]);
        }

        return array_values($items);
    }

    /** @return array{online: bool, status_code: ?int, message: string, master_url: string, wms_url: string} */
    public function testConnection(): array
    {
        try {
            $pricing = $this->request($this->masterUrl().'/api/server/pricing_details', [
                'page' => ['size' => 1, 'number' => 1],
                'filter' => ['loccode' => (string) config('services.care.store_code')],
            ]);
            $inventory = $this->request($this->wmsUrl().'/api/server/inventories/bybin', [
                'page' => ['size' => 1, 'number' => 1],
                'filter' => ['location' => (string) config('services.care.store_code')],
            ]);

            return [
                'online' => $pricing->successful() && $inventory->successful(),
                'status_code' => $pricing->successful() ? $inventory->status() : $pricing->status(),
                'message' => $pricing->successful() && $inventory->successful()
                    ? 'CARE Master dan CARE WMS terhubung.'
                    : sprintf('CARE merespons HTTP %d/%d.', $pricing->status(), $inventory->status()),
                'master_url' => $this->masterUrl(),
                'wms_url' => $this->wmsUrl(),
            ];
        } catch (\Throwable $error) {
            return [
                'online' => false,
                'status_code' => null,
                'message' => 'Koneksi CARE gagal: '.$error->getMessage(),
                'master_url' => $this->masterUrl(),
                'wms_url' => $this->wmsUrl(),
            ];
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function pricingRows(string $sku): array
    {
        $response = $this->request($this->masterUrl().'/api/server/pricing_details', [
            'page' => ['size' => 100, 'number' => 1],
            'filter' => [
                'skucode' => $sku,
                'loccode' => (string) config('services.care.store_code'),
            ],
        ]);
        $this->ensureSuccessful($response, 'CARE Master pricing');

        return array_values(array_filter(
            $this->responseRows($response),
            fn (array $row) => trim((string) ($row['skucode'] ?? '')) === $sku,
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function inventoryRows(string $sku): array
    {
        $response = $this->request($this->wmsUrl().'/api/server/inventories/bybin', [
            'page' => ['size' => 100, 'number' => 1],
            'filter' => [
                'location' => (string) config('services.care.store_code'),
                'search_sku.skucode' => $sku,
            ],
        ]);
        $this->ensureSuccessful($response, 'CARE WMS inventory');

        return array_values(array_filter(
            $this->responseRows($response),
            fn (array $row) => trim((string) ($row['sku_code'] ?? $row['skucode'] ?? $row['sku'] ?? '')) === $sku,
        ));
    }

    /** @return array<int, array<string, mixed>> */
    private function allPricingRows(): array
    {
        return $this->paginatedRows(
            $this->masterUrl().'/api/server/pricing_details',
            ['filter' => ['loccode' => (string) config('services.care.store_code')]],
            'CARE Master pricing',
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function allInventoryRows(): array
    {
        return $this->paginatedRows(
            $this->wmsUrl().'/api/server/inventories/bybin',
            ['filter' => ['location' => (string) config('services.care.store_code')]],
            'CARE WMS inventory',
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function paginatedRows(string $url, array $query, string $label): array
    {
        $rows = [];
        $page = 1;
        $lastPage = 1;

        do {
            $response = $this->request($url, array_replace_recursive($query, [
                'page' => ['size' => 100, 'number' => $page],
            ]));
            $this->ensureSuccessful($response, $label);
            array_push($rows, ...$this->responseRows($response));
            $lastPage = max(1, (int) ($response->json('meta.last_page') ?? 1));
            $page++;
        } while ($page <= $lastPage);

        return $rows;
    }

    private function request(string $url, array $query): Response
    {
        $headers = ['Accept' => 'application/json'];
        if ($serverKey = config('services.care.server_key')) {
            $headers['x-server-key'] = $serverKey;
        }

        return Http::timeout((int) config('services.care.timeout', 10))
            ->withHeaders($headers)
            ->acceptJson()
            ->get($url, $query);
    }

    private function ensureSuccessful(Response $response, string $label): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('%s gagal (HTTP %d).', $label, $response->status()));
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function responseRows(Response $response): array
    {
        $rows = $response->json('data');

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function preferredPrice(array $rows): float
    {
        $storeCode = (string) config('services.care.store_code');
        $now = CarbonImmutable::now();
        $eligible = array_values(array_filter($rows, function (array $row) use ($storeCode, $now) {
            if (! empty($row['deleted_at'])) {
                return false;
            }
            if (! in_array((string) ($row['loccode'] ?? ''), ['', $storeCode], true)) {
                return false;
            }
            $from = empty($row['validfrom']) ? null : CarbonImmutable::parse($row['validfrom'])->startOfDay();
            $to = empty($row['validto']) ? null : CarbonImmutable::parse($row['validto'])->endOfDay();

            return (! $from || $from->lte($now)) && (! $to || $to->gte($now));
        }));
        usort($eligible, function (array $a, array $b) use ($storeCode) {
            $aStore = (string) ($a['loccode'] ?? '') === $storeCode ? 1 : 0;
            $bStore = (string) ($b['loccode'] ?? '') === $storeCode ? 1 : 0;

            return [$bStore, (string) ($b['validfrom'] ?? ''), (int) ($b['id'] ?? 0)]
                <=> [$aStore, (string) ($a['validfrom'] ?? ''), (int) ($a['id'] ?? 0)];
        });

        return (float) ($eligible[0]['articleprice'] ?? 0);
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, float> */
    private function groupPrices(array $rows): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['skucode'] ?? ''));
            if ($sku !== '') {
                $grouped[$sku][] = $row;
            }
        }

        return collect($grouped)->map(fn (array $skuRows) => $this->preferredPrice($skuRows))->all();
    }

    /** @param array<int, array<string, mixed>> $rows @return array<string, array{name: string, stock: int}> */
    private function aggregateInventory(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $sku = trim((string) ($row['sku_code'] ?? $row['skucode'] ?? $row['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            if (($row['bin_status'] ?? 'active') !== 'active') {
                continue;
            }
            if (isset($row['bin_category']) && strtolower((string) $row['bin_category']) !== 'saleable goods') {
                continue;
            }
            $result[$sku] ??= ['name' => (string) ($row['sku_name'] ?? ''), 'stock' => 0];
            if ($result[$sku]['name'] === '' && ! empty($row['sku_name'])) {
                $result[$sku]['name'] = (string) $row['sku_name'];
            }
            $result[$sku]['stock'] += (int) ($row['available_qty'] ?? $row['available_stock'] ?? $row['stock'] ?? 0);
        }

        return $result;
    }

    /** @return array{0: string, 1: string} */
    private function parseVariantName(string $name): array
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*(?:-|,)\s*/', $name) ?: []), 'strlen'));
        if (count($parts) < 3) {
            return ['', ''];
        }

        return [$parts[count($parts) - 2], $parts[count($parts) - 1]];
    }
}
