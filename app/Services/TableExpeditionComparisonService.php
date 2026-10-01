<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TableExpeditionItem;
use App\Support\DeviceProductPayload;
use Illuminate\Support\Collection;

class TableExpeditionComparisonService
{
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

        $activeProductIds = TableExpeditionItem::query()
            ->where('is_active', true)
            ->whereIn('product_id', $ids)
            ->pluck('product_id')
            ->unique();

        $products = Product::query()
            ->whereIn('id', $activeProductIds)
            ->where('is_discontinued', false)
            ->with(DeviceProductPayload::relations())
            ->get()
            ->keyBy('id');

        return $ids
            ->map(fn (int $id): ?Product => $products->get($id))
            ->filter()
            ->values();
    }
}
