<?php

namespace App\Services;

use App\Models\Product;
use App\Models\TableExpeditionItem;
use App\Support\DeviceProductPayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TableExpeditionRecommendationService
{
    /** @return Collection<int, Product> */
    public function for(Product $product, int $limit = 5): Collection
    {
        $product->loadMissing(['activitiesRelation.atomActivity', 'pimRecord']);
        $sourceActivities = $this->activityKeys($product);

        return TableExpeditionItem::query()
            ->where('is_active', true)
            ->where('product_id', '!=', $product->id)
            ->with(array_map(
                fn (string $relation): string => 'product.'.$relation,
                array_unique(array_merge(DeviceProductPayload::relations(), ['rfidTags'])),
            ))
            ->get()
            ->pluck('product')
            ->filter(fn (?Product $candidate): bool => $candidate
                && ! $candidate->is_discontinued
                && (! $candidate->pimRecord || $candidate->pim_catalog_active)
                && ((int) $candidate->stock > 0 || $candidate->variants->sum('stock') > 0))
            ->unique('id')
            ->map(function (Product $candidate) use ($product, $sourceActivities): array {
                $score = 0;
                if ($product->atom_product_sub_category_id
                    && $product->atom_product_sub_category_id === $candidate->atom_product_sub_category_id) {
                    $score += 60;
                } elseif ($product->atom_product_category_id
                    && $product->atom_product_category_id === $candidate->atom_product_category_id) {
                    $score += 40;
                } elseif (filled($product->category)
                    && Str::lower((string) $product->category) === Str::lower((string) $candidate->category)) {
                    $score += 30;
                }

                $score += count(array_intersect($sourceActivities, $this->activityKeys($candidate))) * 15;

                return ['product' => $candidate, 'score' => $score];
            })
            ->filter(fn (array $ranked): bool => $ranked['score'] > 0)
            ->sort(function (array $left, array $right): int {
                $score = $right['score'] <=> $left['score'];

                return $score !== 0 ? $score : $left['product']->id <=> $right['product']->id;
            })
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /** @return list<string> */
    private function activityKeys(Product $product): array
    {
        return $product->activitiesRelation
            ->map(fn ($activity): string => Str::slug((string) ($activity->atomActivity?->slug ?: $activity->name)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
