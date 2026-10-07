<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AtomCatalogClient
{
    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        $page = 1;
        $lastPage = 1;
        $products = [];
        $size = max(10, (int) config('atom.catalog_page_size', 1000));

        do {
            $response = $this->request()->get('products', ['page' => $page, 'size' => $size])->throw()->json();
            foreach ($response['data'] ?? [] as $row) {
                if (is_array($row['attributes'] ?? null)) {
                    $products[] = $this->catalogItem($row['attributes'], $row['id'] ?? null);
                }
            }
            $lastPage = max(1, (int) data_get($response, 'meta.totalPage', 1));
            $page++;
        } while ($page <= $lastPage);

        return $products;
    }

    /** @return array<string, mixed> */
    public function product(string $slug): array
    {
        $response = $this->request()->get('products/'.rawurlencode($slug))->throw()->json();

        return $this->single($response, 'detail', $slug);
    }

    /** @return array<int, array<string, mixed>> */
    public function variants(string $slug): array
    {
        return $this->collection('products/'.rawurlencode($slug).'/variants');
    }

    /** @return array<int, array<string, mixed>> */
    public function variantImages(string $slug): array
    {
        return $this->collection('products/'.rawurlencode($slug).'/variant-images');
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('atom.base_url'), '/'))
            ->acceptJson()
            ->withOptions(['verify' => (bool) config('atom.verify_tls', true)])
            ->connectTimeout((int) config('atom.connect_timeout', 10))
            ->timeout((int) config('atom.timeout', 120))
            ->retry(
                max(1, (int) config('atom.attempts', 4)),
                fn (int $attempt) => min(250 * (2 ** ($attempt - 1)), 2000),
                throw: false,
            );
    }

    /** @return array<string, mixed> */
    private function catalogItem(array $attributes, mixed $id): array
    {
        return [
            'atom_id' => $id,
            'skuProduct' => $attributes['skuProduct'] ?? null,
            'name' => $attributes['name'] ?? null,
            'slug' => $attributes['slug'] ?? null,
            'soldCount' => $attributes['soldCount'] ?? 0,
            'activity' => $attributes['activity'] ?? null,
            'category' => $attributes['category'] ?? null,
            'subCategory' => $attributes['subCategory'] ?? null,
            'type' => $attributes['type'] ?? null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function collection(string $path): array
    {
        $response = $this->request()->get($path)->throw()->json();

        return collect($response['data'] ?? [])->map(function ($row) {
            return is_array($row['attributes'] ?? null)
                ? $row['attributes'] + ['atom_id' => $row['id'] ?? null]
                : null;
        })->filter()->values()->all();
    }

    /** @return array<string, mixed> */
    private function single(mixed $response, string $resource, string $slug): array
    {
        $attributes = data_get($response, 'data.attributes');
        if (! is_array($attributes)) {
            throw new RuntimeException("ATOM {$resource} tidak valid untuk slug {$slug}.");
        }

        return $attributes + ['atom_id' => data_get($response, 'data.id')];
    }
}
