<?php

namespace App\Services;

use App\Models\AtomProductActivity;
use App\Models\AtomProductActivityGroup;
use App\Models\AtomProductCategory;
use App\Models\AtomProductSubCategory;
use Illuminate\Support\Str;

class AtomMasterDataResolver
{
    /** @return array{category_id: ?int, sub_category_id: ?int} */
    public function category(?string $value): array
    {
        $value = trim((string) $value);
        if ($value === '') return ['category_id' => null, 'sub_category_id' => null];
        $needle = Str::lower($value);
        $category = AtomProductCategory::query()->get()->first(fn ($item) => $this->matches($needle, [
            $item->external_id, $item->name, $item->slug,
        ]));
        if ($category) return ['category_id' => $category->id, 'sub_category_id' => null];

        $subCategory = AtomProductSubCategory::query()->with('category')->get()->first(fn ($item) => $this->matches($needle, [
            $item->external_id, $item->name, $item->slug,
        ]));
        return [
            'category_id' => $subCategory?->category_id,
            'sub_category_id' => $subCategory?->id,
        ];
    }

    public function activity(array $row): ?AtomProductActivity
    {
        $values = array_values(array_filter([
            $row['id'] ?? null, $row['external_id'] ?? null, $row['name'] ?? null, $row['slug'] ?? null,
        ], fn ($value) => trim((string) $value) !== ''));

        foreach ($values as $value) {
            $needle = Str::lower(trim((string) $value));
            $activity = AtomProductActivity::query()->get()->first(fn ($item) => $this->matches($needle, [
                $item->source_id, $item->external_id, $item->name, $item->slug,
            ]));
            if ($activity) return $activity;
        }
        return null;
    }

    /** @return array<int, AtomProductActivity> */
    public function activitiesFromValue(?string $value): array
    {
        $values = preg_split('/\s*[,;|]\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $resolved = [];
        foreach ($values as $value) {
            $activity = $this->activity(['name' => $value]);
            if (! $activity) {
                $needle = Str::lower($value);
                $group = AtomProductActivityGroup::query()->with('activities')->get()->first(fn ($item) => $this->matches($needle, [
                    $item->name, $item->slug,
                ]));
                $activity = $group?->activities->first();
            }
            if ($activity) $resolved[$activity->id] = $activity;
        }
        return array_values($resolved);
    }

    private function matches(string $needle, array $values): bool
    {
        foreach ($values as $value) {
            $candidate = Str::lower(trim((string) $value));
            if ($candidate === $needle || Str::slug($candidate) === Str::slug($needle)) return true;
        }
        return false;
    }
}
