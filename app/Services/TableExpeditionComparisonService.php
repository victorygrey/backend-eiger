<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TableExpeditionItem;
use App\Support\DeviceProductPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TableExpeditionComparisonService
{
    public function __construct(private TableExpeditionProductType $types) {}

    public function eligibleQuery(): Builder
    {
        return Product::query()->where('is_discontinued', false)
            ->where(fn (Builder $products) => $products->whereDoesntHave('pimRecord')->orWhere('pim_catalog_active', true))
            ->with(['atomCategory', 'atomSubCategory']);
    }

    public function isSameType(Product $primary, Product $candidate): bool
    {
        return $this->types->matches($primary, $candidate);
    }

    public function type(Product $product): ?array
    {
        return $this->types->resolve($product);
    }

    /** @return Collection<int, Product> */
    public function for(TableExpeditionItem $item): Collection
    {
        $ids = collect($item->similar_product_ids ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $item->product_id)
            ->unique()
            ->take(5)
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $item->loadMissing('product');
        if (! $item->product) {
            return collect();
        }
        $products = $this->eligibleQuery()
            ->whereIn('id', $ids)
            ->get()
            ->filter(fn (Product $product): bool => $this->isSameType($item->product, $product))
            ->load(DeviceProductPayload::relations())
            ->keyBy('id');

        return $ids
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter()
            ->values();
    }
}
