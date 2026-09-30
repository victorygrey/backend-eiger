<?php

namespace App\Services;

use App\Models\AtomProductActivity;
use App\Models\AtomProductActivityGroup;
use App\Models\AtomProductCategory;
use App\Models\AtomProductSubCategory;
use Illuminate\Support\Str;

class AtomMasterDataResolver
{
    /**
     * Translate one or more PIM codes to their canonical labels from the imported ATOM master tables.
     *
     * @return array<int, array{code: string, name: string, type: string, parent: ?string}>
     */
    public function displayValues(?string $value, ?string $attributeCode = null): array
    {
        $value = trim((string) $value);
        if ($value === '') return [];

        $decoded = json_decode($value, true);
        $tokens = is_array($decoded)
            ? collect($decoded)->flatten()->filter(fn ($item) => is_scalar($item))->map(fn ($item) => trim((string) $item))
            : collect(preg_split('/\s*[,;|]\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [$value]);
        $tokens = $tokens->filter()->unique()->values();
        $attributeCode = Str::lower(trim((string) $attributeCode));
        $guided = str_contains($attributeCode, 'categor') || str_contains($attributeCode, 'activit');

        // Avoid treating ordinary prose and tag lists as master codes. Known
        // category/activity fields are always resolved, while other attributes
        // are resolved only when their value has the shape of an ATOM code.
        if (! $guided) {
            $tokens = $tokens->filter(fn ($token) => preg_match('/^(?:[a-z]+\d[a-z0-9_-]*|\d{1,10})$/i', $token));
        }

        $sets = [];
        if (! str_contains($attributeCode, 'activit')) {
            $sets[] = ['type' => 'Category', 'items' => AtomProductCategory::query()->get(), 'parent' => fn () => null];
            $sets[] = ['type' => 'Subcategory', 'items' => AtomProductSubCategory::query()->with('category')->get(), 'parent' => fn ($item) => $item->category?->name];
        }
        if (! str_contains($attributeCode, 'categor')) {
            $sets[] = ['type' => 'Activity Group', 'items' => AtomProductActivityGroup::query()->get(), 'parent' => fn () => null];
            $sets[] = ['type' => 'Activity', 'items' => AtomProductActivity::query()->with('group')->get(), 'parent' => fn ($item) => $item->group?->name];
        }

        $resolved = [];
        foreach ($tokens as $token) {
            $needle = Str::lower($token);
            foreach ($sets as $set) {
                $match = $set['items']->first(fn ($item) => $this->matches($needle, [
                    $item->source_id, $item->external_id, $item->name, $item->slug,
                ]));
                if (! $match) continue;
                $resolved[] = [
                    'code' => (string) $token,
                    'name' => $match->name,
                    'type' => $set['type'],
                    'parent' => ($set['parent'])($match),
                ];
                break;
            }
        }

        return collect($resolved)->unique(fn ($item) => $item['type'].'|'.$item['name'])->values()->all();
    }

    /** @return array{category_id: ?int, sub_category_id: ?int, category_name: ?string, sub_category_name: ?string} */
    public function category(?string $categoryValue, ?string $subCategoryValue = null): array
    {
        $categoryValue = trim((string) $categoryValue);
        $subCategoryValue = trim((string) $subCategoryValue);
        $categories = AtomProductCategory::query()->get();
        $subCategories = AtomProductSubCategory::query()->with('category')->get();

        $subCategory = null;
        foreach (array_filter([$subCategoryValue, $categoryValue]) as $value) {
            $needle = Str::lower($value);
            $subCategory = $subCategories->first(fn ($item) => $this->matches($needle, [
                $item->source_id, $item->external_id, $item->name, $item->slug,
            ]));
            if ($subCategory) break;
        }

        if ($subCategory) {
            return [
                'category_id' => $subCategory->category_id,
                'sub_category_id' => $subCategory->id,
                'category_name' => $subCategory->category?->name,
                'sub_category_name' => $subCategory->name,
            ];
        }

        $category = null;
        foreach (array_filter([$categoryValue, $subCategoryValue]) as $value) {
            $needle = Str::lower($value);
            $category = $categories->first(fn ($item) => $this->matches($needle, [
                $item->source_id, $item->external_id, $item->name, $item->slug,
            ]));
            if ($category) break;
        }

        return [
            'category_id' => $category?->id,
            'sub_category_id' => null,
            'category_name' => $category?->name,
            'sub_category_name' => null,
        ];
    }

    public function activity(array $row): ?AtomProductActivity
    {
        $values = array_values(array_filter([
            $row['external_id'] ?? null, $row['externalId'] ?? null, $row['code'] ?? null,
            $row['activity_code'] ?? null, $row['activityCode'] ?? null, $row['source_id'] ?? null,
            $row['id'] ?? null, $row['name'] ?? null, $row['slug'] ?? null,
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
                $groups = AtomProductActivityGroup::query()->with('activities')->get()->filter(fn ($item) => $this->matches($needle, [
                    $item->source_id, $item->external_id, $item->name, $item->slug,
                ]));
                // Some official group external IDs are reused. Resolve a group code only
                // when it identifies one row; ambiguous codes remain available in raw PIM data.
                $activity = $groups->count() === 1 ? $groups->first()?->activities->first() : null;
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
